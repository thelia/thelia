<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Company\Checkout;

use Propel\Runtime\Exception\PropelException;
use Psr\Clock\ClockInterface;
use Thelia\Domain\Checkout\Service\ExpressCheckoutTotal;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepViolationCollectorInterface;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderRefusedException;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingJudge;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingReader;
use Thelia\Domain\Company\Checkout\Service\SpendingAmount;
use Thelia\Domain\Company\Checkout\Service\SpendingPeriod;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\Cart;

/**
 * What the company of a buyer lets them order: the company must be active, the role must allow
 * the order, and the order, with what the buyer already ordered over the period, must stay
 * within their limits.
 *
 * A cart whose buyer belongs to no company has nothing to settle here: the step is left out of
 * its tunnel and answers nothing. The placement asks it for every cart whatever is shown, and
 * asks it once more, locked, when it writes the order ({@see Service\CompanyPlacementGuard}).
 *
 * The amount is the total the tunnel charges, postage and gift wrapping included, brought back
 * to the default currency the limits are written in. A cart not priced yet — no carrier, no
 * address — is judged on what does not depend on an amount.
 */
final readonly class CompanySpendingStepProvider implements CheckoutStepProviderInterface, CheckoutStepViolationCollectorInterface
{
    public const string CODE = 'company.spending_limit';

    public function __construct(
        private CompanyMembershipReader $membershipReader,
        private CompanySpendingJudge $judge,
        private CompanySpendingReader $spending,
        private ExpressCheckoutTotal $checkoutTotal,
        private ClockInterface $clock,
    ) {
    }

    public function code(): string
    {
        return self::CODE;
    }

    /**
     * Next to the delivery, whose postage is part of the amount judged, and before the payment.
     */
    public function defaultPosition(): int
    {
        return 2;
    }

    public function isMandatory(): bool
    {
        return true;
    }

    public function isSkippedFor(Cart $cart): bool
    {
        return null === $this->membershipOf($cart);
    }

    public function check(Cart $cart): void
    {
        $refusals = $this->collectRefusals($cart);

        if ([] !== $refusals) {
            throw $refusals[0];
        }
    }

    /**
     * @return list<CompanyOrderRefusedException>
     *
     * @throws PropelException
     */
    public function collectRefusals(Cart $cart): array
    {
        $membership = $this->membershipOf($cart);

        if (null === $membership) {
            return [];
        }

        return $this->judge->refusalsFor(
            $membership,
            $this->totalOf($cart),
            fn (): string => $this->spending->spentSince(
                $membership->companyId,
                $membership->customerId,
                SpendingPeriod::from((string) $membership->period)->startAt($this->clock->now()),
            ),
        );
    }

    public function componentName(): ?string
    {
        return null;
    }

    private function membershipOf(Cart $cart): ?CompanyMembership
    {
        $customerId = $cart->getCustomerId();

        return null === $customerId ? null : $this->membershipReader->membershipOf((int) $customerId);
    }

    /**
     * @throws PropelException
     */
    private function totalOf(Cart $cart): ?string
    {
        $total = $this->checkoutTotal->of($cart);

        if (null === $total) {
            return null;
        }

        $rate = $cart->getCurrency()?->getRate() ?? 1.0;

        return SpendingAmount::fromFloat($total / ($rate > 0 ? $rate : 1.0));
    }
}

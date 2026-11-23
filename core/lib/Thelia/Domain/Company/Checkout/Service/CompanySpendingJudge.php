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

namespace Thelia\Domain\Company\Checkout\Service;

use Thelia\Core\Template\Helper\FormatService;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderNotPermittedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderRefusedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyPeriodLimitExceededException;
use Thelia\Domain\Company\Checkout\Exception\CompanySuspendedException;
use Thelia\Domain\Company\Checkout\Exception\CompanyWithoutBillingAddressException;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Domain\Company\Service\CompanyAmount;
use Thelia\Model\Currency;

/**
 * Whether the company of a buyer lets them place an order of a given amount.
 *
 * The one rule the tunnel asks about a cart and the placement asks again about the order it
 * is writing, so that the two cannot disagree. It reads nothing but the symbol of the default currency: the amount of the order and
 * what the buyer already spent over the period are handed to it, the second one only when a
 * limit over a period makes it matter.
 */
final readonly class CompanySpendingJudge
{
    /**
     * @param FormatService|null $format writes the amounts of a refusal as the shop writes a price in the
     *                                   default currency; without it they are written as plain numbers
     */
    public function __construct(private ?FormatService $format = null)
    {
    }

    /**
     * Every reason this order is refused, in the order the buyer meets them: a suspended
     * company, a company without a billing address or a role without the permission say everything there is to say, and the limits
     * are only read for a buyer who may order.
     *
     * @param string|null        $total         what the order costs in the default currency, null while
     *                                          the cart is not priced yet (no carrier, no address): only
     *                                          what does not depend on the amount is judged
     * @param callable(): string $spentInPeriod what the buyer's orders of the current period already cost,
     *                                          in the default currency
     *
     * @return list<CompanyOrderRefusedException>
     */
    public function refusalsFor(CompanyMembership $membership, ?string $total, callable $spentInPeriod): array
    {
        if (!$membership->companyActive) {
            return [new CompanySuspendedException()];
        }

        if (!$membership->companyHasBillingAddress) {
            return [new CompanyWithoutBillingAddressException()];
        }

        if (!$membership->has(CompanyPermission::ORDER_PLACE)) {
            return [new CompanyOrderNotPermittedException()];
        }

        if (null === $total) {
            return [];
        }

        $refusals = [];

        if (null !== $membership->orderLimit && CompanyAmount::compare($total, $membership->orderLimit) > 0) {
            $refusals[] = new CompanyOrderLimitExceededException(
                SpendingAmount::display($membership->orderLimit),
                SpendingAmount::display($total),
                shown: [
                    'limit' => $this->shown($membership->orderLimit),
                    'total' => $this->shown($total),
                ],
            );
        }

        if (null !== $membership->periodLimit && null !== $membership->period) {
            $spent = $spentInPeriod();

            if (CompanyAmount::compare(SpendingAmount::add($spent, $total), $membership->periodLimit) > 0) {
                $refusals[] = new CompanyPeriodLimitExceededException(
                    SpendingAmount::display($membership->periodLimit),
                    $membership->period,
                    SpendingAmount::display($spent),
                    SpendingAmount::display($total),
                    shown: [
                        'limit' => $this->shown($membership->periodLimit),
                        'spent' => $this->shown($spent),
                        'total' => $this->shown($total),
                    ],
                );
            }
        }

        return $refusals;
    }

    private function shown(string $amount): string
    {
        return $this->format?->price($amount, Currency::getDefaultCurrency()->getId(), Translator::getInstance()->getLocale()) ?? SpendingAmount::display($amount);
    }
}

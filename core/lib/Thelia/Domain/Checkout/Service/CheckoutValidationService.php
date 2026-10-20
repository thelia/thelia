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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\DTO\CheckoutViolation;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepViolationCollectorInterface;
use Thelia\Model\Cart;

/**
 * The last word before an order is placed.
 *
 * Every step the installed code declares is asked about the cart, in the order the
 * tunnel serves them in, the one `CheckoutProgressionService` resolves from the
 * `checkout_step` rows and the positions the steps declare, and the first refusal is the
 * answer. The table orders the checks and never selects them, and that is the point:
 * turning a step off removes its screen, never its check, so an order the step would
 * have refused is refused whether or not the merchant still shows it.
 * `isSkippedFor()` is ignored for the same reason — it answers
 * "is there a question to ask this buyer", not "may this order go through": the delivery
 * step of a virtual cart is skipped on screen and still demands the carrier the order
 * cannot be shipped without.
 *
 * A module adding a step therefore gets its check made at placement by shipping the
 * provider and nothing else, which is what this used to promise while enumerating the
 * four core guards by hand.
 */
readonly class CheckoutValidationService
{
    public function __construct(
        private CheckoutProgressionService $progression,
    ) {
    }

    /**
     * @throws CheckoutException on the first step this cart has not satisfied
     * @throws PropelException
     */
    public function validateForOrder(Cart $cart): void
    {
        foreach ($this->orderedProviders() as $provider) {
            $provider->check($cart);
        }
    }

    /**
     * The same questions, asked of the same steps, with every refusal gathered instead
     * of the first one raised.
     *
     * The tunnel of a theme has one screen per step and stops at the first refusal, which
     * is what validateForOrder() is for and why its contract is left alone. A caller with
     * no screens — the front API — hands over a whole checkout in one request, and
     * sending it back one missing thing at a time would make the buyer pay for a round
     * trip per field.
     *
     * Reported in the order of the tunnel, so the first violation of the list is still
     * the first thing the buyer has to go back and do.
     *
     * @return list<CheckoutViolation> empty when this cart may be ordered
     *
     * @throws PropelException
     */
    public function collectViolations(Cart $cart): array
    {
        $violations = [];

        foreach ($this->orderedProviders() as $provider) {
            foreach ($this->refusalsOf($provider, $cart) as $refusal) {
                $violations[] = CheckoutViolation::fromRefusal($provider->code(), $refusal);
            }
        }

        return $violations;
    }

    /**
     * @return list<CheckoutException>
     *
     * @throws PropelException
     */
    private function refusalsOf(CheckoutStepProviderInterface $provider, Cart $cart): array
    {
        if ($provider instanceof CheckoutStepViolationCollectorInterface) {
            return $provider->collectRefusals($cart);
        }

        try {
            $provider->check($cart);
        } catch (CheckoutException $refusal) {
            return [$refusal];
        }

        return [];
    }

    /**
     * The steps in the order the buyer met them, so that a buyer who still has an
     * address or a carrier to choose is told about that first rather than about a box
     * further down the tunnel they never reached.
     *
     * @return list<CheckoutStepProviderInterface>
     *
     * @throws PropelException
     */
    private function orderedProviders(): array
    {
        return $this->progression->providersInTunnelOrder();
    }
}

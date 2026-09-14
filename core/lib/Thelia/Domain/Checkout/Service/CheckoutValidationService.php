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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\Cart;

/**
 * The last word before an order is placed.
 *
 * Every step the installed code declares is asked about the cart, in the order the
 * tunnel declares them in, and the first refusal is the answer. Nothing here reads the
 * `checkout_step` table, and that is the point: turning a step off removes its screen,
 * never its check, so an order the step would have refused is refused whether or not the
 * merchant still shows it. `isSkippedFor()` is ignored for the same reason — it answers
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
    /**
     * @param iterable<CheckoutStepProviderInterface> $stepProviders
     */
    public function __construct(
        #[AutowireIterator('thelia.checkout.step_provider')]
        private iterable $stepProviders,
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
     * The steps in the order the buyer met them, so that a buyer who still has an
     * address or a carrier to choose is told about that first rather than about a box
     * further down the tunnel they never reached.
     *
     * The declared position is what orders them, not the table: the table can be missing
     * a row, and this has to answer the same whatever state it is in.
     *
     * @return list<CheckoutStepProviderInterface>
     */
    private function orderedProviders(): array
    {
        $providers = iterator_to_array($this->stepProviders, false);

        // Two steps declaring the same position is not a reason to check them in
        // whichever order the container happened to build them in.
        usort(
            $providers,
            static fn (CheckoutStepProviderInterface $left, CheckoutStepProviderInterface $right): int => [$left->defaultPosition(), $left->code()] <=> [$right->defaultPosition(), $right->code()],
        );

        return $providers;
    }
}

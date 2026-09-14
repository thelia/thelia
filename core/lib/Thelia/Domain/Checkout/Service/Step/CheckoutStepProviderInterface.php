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

namespace Thelia\Domain\Checkout\Service\Step;

use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Model\Cart;

/**
 * Declares one step of the checkout: what it is called, where it belongs in the tunnel,
 * and what a cart still has to satisfy before it may be left behind.
 *
 * Implementations are auto-registered: any service implementing this interface is
 * tagged `thelia.checkout.step_provider` and read by CheckoutProgressionService, which
 * joins it to the `checkout_step` row of the same code. The row is what the merchant
 * edits — the order, the wording, whether the step is asked for at all; the provider is
 * what the code knows about it, and the fallback when the table has no row for it.
 *
 * Core declares the four steps of the checkout it ships with. Everything else — a gift
 * message screen, a click-and-collect slot picker, an age check — belongs to the module
 * that owns it, hence this interface.
 */
interface CheckoutStepProviderInterface
{
    /**
     * The name everything else refers this step by: the `checkout_step` row, the route
     * of the theme, the progression guard.
     *
     * It must not collide with a core step (cart, delivery, payment, confirmation) nor
     * with another module's: prefer the module code as a prefix, for instance
     * `loyalty_redeem`.
     */
    public function code(): string;

    /**
     * Where the step belongs when no row names a position for it — the position the
     * synchronisation writes when it creates the row, and the one the progression uses
     * while there is none.
     *
     * Core leaves 1 to the cart and reserves the last two for the payment and the
     * confirmation, so a step of a module belongs somewhere in between.
     */
    public function defaultPosition(): int;

    /**
     * Whether the shop may not do without this step. A mandatory step is refused
     * deactivation in the back office.
     */
    public function isMandatory(): bool;

    /**
     * Whether this particular cart has nothing to do at this step, so that it is left
     * out of the tunnel rather than shown as an empty screen. The delivery step answers
     * true for a cart holding nothing to ship.
     *
     * This is about the cart, not about the configuration: a step the merchant turned
     * off is dropped from the list whatever this answers.
     */
    public function isSkippedFor(Cart $cart): bool;

    /**
     * Refuses the cart while this step is not settled, by throwing.
     *
     * The progression calls this in order to find the first step the buyer still has
     * something to do at, so the exception is the answer, not an incident: it carries
     * the sentence the buyer reads. A terminal step with nothing left to check — the
     * confirmation — does nothing here.
     *
     * @throws CheckoutException while the cart has not satisfied this step
     */
    public function check(Cart $cart): void;

    /**
     * The component the theme renders this step with, as a hint and nothing more: a
     * theme is free to ignore it, and answering null means the theme decides on its own.
     *
     * The core steps answer null on purpose. A component name belongs to a theme, and
     * the core has no business knowing how Flexy, or any other theme, names its own: the
     * theme maps the four core codes itself. It is a module shipping a step no theme has
     * ever heard of that has something useful to say here, and a theme that recognises
     * neither the code nor the hint still has the code to fall back on.
     */
    public function componentName(): ?string;
}

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
 * Implemented by a step that asks a cart more than one question, and can therefore have
 * more than one thing to say about it.
 *
 * `check()` answers the tunnel of a theme: one screen, one refusal, the buyer goes back
 * and fixes it. A caller with no screens — the front API — fills a whole form in one
 * shot and needs the whole list, and a step that runs three independent guards would
 * otherwise send it round three times.
 *
 * Optional on purpose: a step that has a single thing to check, or whose checks build on
 * one another the way the delivery does (no address, so no carrier to judge, so no quote
 * to ask for), says nothing here and is asked through `check()` like before.
 */
interface CheckoutStepViolationCollectorInterface
{
    /**
     * Every refusal this step has for the cart, in the order the buyer meets them on the
     * screen, and an empty list when it is satisfied.
     *
     * @return list<CheckoutException>
     */
    public function collectRefusals(Cart $cart): array;
}

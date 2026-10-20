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

namespace Thelia\Core\Event\Checkout;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Model\Cart;

/**
 * A wallet's payment the module vouched for, and that did not lead to `pay()` for a new
 * order of this confirmation.
 *
 * The module reads its provider's answer before the shop places anything, and the
 * placement can still fail after it: a stock that ran out, a placement already running,
 * a total that differs, a buyer nobody identified. Or the cart was already ordered by an
 * earlier confirmation, and the shop does not call the module a second time. A module
 * whose provider holds money by then, an authorization waiting for its capture, listens
 * for this to release it: otherwise the buyer is charged for an order that does not exist.
 *
 * The failure can also come after the order was written, from `pay()` itself or a listener
 * of the payment. Release only an authorization the module still holds, never one `pay()`
 * already captured.
 *
 * A refusal the module made itself is not reported: nothing was taken.
 */
class ExpressPaymentNotPlacedEvent extends ActionEvent
{
    public function __construct(
        private readonly string $paymentModuleCode,
        private readonly Cart $cart,
        private readonly ExpressWalletAnswer $answer,
        private readonly ?\Throwable $reason,
    ) {
    }

    public function getPaymentModuleCode(): string
    {
        return $this->paymentModuleCode;
    }

    /** The cart the module was handed when it read its provider's answer. */
    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getAnswer(): ExpressWalletAnswer
    {
        return $this->answer;
    }

    /** Why nothing was placed, as the shop answers the script; null when the cart was already ordered. */
    public function getReason(): ?\Throwable
    {
        return $this->reason;
    }

    public function wasAlreadyPlaced(): bool
    {
        return null === $this->reason;
    }
}

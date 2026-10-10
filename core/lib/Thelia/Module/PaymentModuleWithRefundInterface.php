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

namespace Thelia\Module;

use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\RefundReason;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;

/**
 * A payment module that gives money back through its provider.
 *
 * Optional, like the capture: published modules keep working untouched, and the shop records
 * their refunds by hand. The core has already bounded the amount by what the journal says
 * was collected and not given back, and written $transaction as pending; the module calls
 * the provider and reports the outcome, the same way as a capture: a refusal through the
 * result or a PaymentRefusedException, a call without answer by any other exception, which
 * leaves the line pending until the provider's notification settles it.
 */
interface PaymentModuleWithRefundInterface extends PaymentModuleInterface
{
    /**
     * Whether, as currently configured, the module refunds through its provider.
     */
    public function supportsRefund(): bool;

    /**
     * @param string|null $comment written by the merchant, cleaned and at most 255 characters
     */
    public function refund(Order $order, float $amount, OrderPaymentTransaction $transaction, RefundReason $reason, ?string $comment): PaymentOperationResult;
}

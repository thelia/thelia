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
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;

/**
 * A payment module that can reserve an amount at payment time and take it later,
 * in one or several captures.
 *
 * Optional on purpose: it is not part of PaymentModuleInterface, so every published
 * module keeps working untouched. A module that implements it writes the authorization
 * to the order's payment journal itself, through
 * Thelia\Domain\Payment\Service\PaymentTransactionRecorder, when the provider confirms
 * it; the core then offers the capture in the back office and the admin API, and
 * records each capture around the call made here.
 *
 * What the journal expects from such a module:
 * - every movement it reports carries the reference the provider gave it, and a new
 *   attempt carries a new reference: the journal answers a known reference with the line
 *   it already holds, and refuses it for another outcome or another amount;
 * - the recorder is never called inside a database transaction the module opened: the
 *   lock of the journal would be released before that transaction commits;
 * - while the authorization still holds an amount, a capture the module switches off
 *   (supportsDeferredCapture() turning false) leaves that amount to be captured at the
 *   provider: marking the order paid takes nothing.
 */
interface PaymentModuleWithCaptureInterface extends PaymentModuleInterface
{
    /**
     * Whether, as currently configured, the module authorizes first and captures
     * later. A module can expose the choice to the merchant and answer false when
     * it is set to take the price at once; the core then treats it like any other.
     */
    public function supportsDeferredCapture(): bool;

    /**
     * Takes $amount from what the order's authorization still holds.
     *
     * The core has already checked the amount against the authorization and the
     * smallest coin of the currency, and written $transaction as pending; the module
     * calls the provider and reports the outcome, a refusal included, through the
     * returned PaymentOperationResult. A PaymentRefusedException thrown here is a refusal
     * too: the line is settled as failed with its message, and the exception rethrown.
     * Any other exception leaves the line pending — the call may have reached the
     * provider — until the provider's notification settles it, and reaches the caller as
     * a PaymentProviderUnreachableException wrapping it.
     */
    public function capture(Order $order, float $amount, OrderPaymentTransaction $transaction): PaymentOperationResult;

    /**
     * Releases what the order's authorization still holds, without taking it.
     */
    public function voidAuthorization(Order $order, OrderPaymentTransaction $transaction): PaymentOperationResult;
}

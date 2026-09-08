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
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\IncompleteInvoiceAddressException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Domain\Checkout\Exception\MissingConsentException;
use Thelia\Model\Cart;

readonly class CheckoutValidationService
{
    public function __construct(
        private CartGuard $cartGuard,
        private ConsentGuard $consentGuard,
    ) {
    }

    /**
     * @throws EmptyCartException
     * @throws MissingAddressException
     * @throws IncompleteInvoiceAddressException
     * @throws InvalidDeliveryException
     * @throws InvalidPaymentException
     * @throws MissingConsentException
     * @throws PropelException
     */
    public function validateForOrder(Cart $cart): void
    {
        $this->cartGuard->checkCartNotEmpty($cart);
        $this->cartGuard->checkValidDelivery($cart);
        $this->cartGuard->checkInvoiceAddressLegalIdentifiers($cart);
        $this->cartGuard->checkValidPayment($cart);

        // Last, so that a buyer who still has an address or a delivery choice to make is
        // told about that first rather than about a box they have not reached yet.
        $this->consentGuard->checkMandatoryConsentsAccepted();
    }
}

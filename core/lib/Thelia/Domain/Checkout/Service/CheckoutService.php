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
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;

readonly class CheckoutService
{
    public function __construct(
        private CheckoutValidationService $validationService,
        private CheckoutResetService $resetService,
        private CheckoutPaymentService $paymentService,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function pay(
        int $deliveryAddressId,
        int $invoiceAddressId,
        int $deliveryModuleId,
        int $paymentModuleId,
    ): ?Response {
        $this->validateForOrder();

        return $this->paymentService->pay(
            $deliveryAddressId,
            $invoiceAddressId,
            $deliveryModuleId,
            $paymentModuleId
        );
    }

    public function resetCheckout(): void
    {
        $this->resetService->reset();
    }

    /**
     * @throws EmptyCartException
     * @throws MissingAddressException
     * @throws InvalidDeliveryException
     * @throws InvalidPaymentException
     * @throws PropelException
     */
    public function validateForOrder(): void
    {
        $this->validationService->validateForOrder();
    }
}

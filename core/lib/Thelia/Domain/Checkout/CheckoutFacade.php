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

namespace Thelia\Domain\Checkout;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;

final readonly class CheckoutFacade
{
    public function __construct(
        private CartSelectionService $cartSelectionService,
        private CheckoutValidationService $validationService,
        private CheckoutPaymentService $paymentService,
        private PostageHandler $postageHandler,
    ) {
    }

    /**
     * Select the delivery address on the cart and refresh shipping.
     */
    public function selectDeliveryAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryAddress($dto);
    }

    /**
     * Select the invoice address on the cart and refresh shipping.
     */
    public function selectInvoiceAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setInvoiceAddress($dto);
    }

    /**
     * Select the delivery module on the cart and refresh shipping.
     */
    public function selectDeliveryModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryModule($dto);
    }

    /**
     * Select the payment module on the cart and refresh shipping.
     */
    public function selectPaymentModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setPaymentModule($dto);
    }

    /**
     * Validate the cart is ready for order placement (items, delivery, invoice address, payment).
     *
     * @throws \Exception If underlying services raise domain exceptions
     */
    public function validateForOrder(Cart $cart): void
    {
        $this->validationService->validateForOrder($cart);
    }

    /**
     * Reset checkout selections on the given cart and clear postage.
     */
    public function resetCheckout(Cart $cart): void
    {
        $cart
            ->setDeliveryModuleId(null)
            ->setAddressDeliveryId(null)
            ->setAddressInvoiceId(null)
            ->setPaymentModuleId(null)
            ->save();

        $this->postageHandler->clearCartPostage($cart);
    }

    /**
     * Place and pay the order based on checkout selections.
     *
     * Expects the DTO to carry all necessary identifiers (deliveryAddressId, invoiceAddressId,
     * deliveryModuleId, paymentModuleId) already chosen on the cart.
     *
     * @return Response|null The payment response if available
     *
     * @throws \Exception If underlying services raise domain exceptions
     */
    public function pay(CheckoutDTO $dto): ?Response
    {
        $this->validateForOrder($dto->getCart());

        return $this->paymentService->pay(
            $dto->getCart(),
            $dto->getDeliveryAddressId() ?? $dto->getCart()->getAddressDeliveryId(),
            $dto->getInvoiceAddressId() ?? $dto->getCart()->getAddressInvoiceId(),
            $dto->getDeliveryModuleId() ?? $dto->getCart()->getDeliveryModuleId(),
            $dto->getPaymentModuleId() ?? $dto->getCart()->getPaymentModuleId(),
        );
    }
}

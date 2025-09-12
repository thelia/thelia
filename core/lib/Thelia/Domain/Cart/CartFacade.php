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

namespace Thelia\Domain\Cart;

use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Cart\Service\CartItemService;
use Thelia\Domain\Cart\Service\CartRetriever;
use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

final readonly class CartFacade
{
    public function __construct(
        private CartItemService $cartItemService,
        private CartSelectionService $cartSelectionService,
        private PostageHandler $postageHandler,
        private CartRetriever $cartRetriever,
    ) {
    }

    /**
     * Add an item to cart and refresh shipping if needed.
     */
    public function addItem(CartItemAddDTO $dto): void
    {
        $this->cartItemService->addItem($dto);
    }

    /**
     * Remove an item from cart and refresh shipping if needed.
     */
    public function removeItem(CartItemDeleteDTO $dto): void
    {
        $this->cartItemService->deleteItem($dto);
    }

    /**
     * Update an item quantity in cart and refresh shipping if needed.
     *
     * @throws NotEnoughStockException
     */
    public function updateItemQuantity(CartItemUpdateQuantityDTO $dto): void
    {
        $this->cartItemService->updateQuantityItem($dto);
    }

    /**
     * Select delivery address on cart and refresh shipping.
     * Use CheckoutDTO with cart + deliveryAddressId filled.
     */
    public function setDeliveryAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryAddress($dto);
    }

    /**
     * Select invoice address on cart and refresh shipping.
     * Use CheckoutDTO with cart + invoiceAddressId filled.
     */
    public function setInvoiceAddress(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setInvoiceAddress($dto);
    }

    /**
     * Select delivery module on cart and refresh shipping.
     * Use CheckoutDTO with cart + deliveryModuleId filled.
     */
    public function setDeliveryModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setDeliveryModule($dto);
    }

    /**
     * Select payment module on cart and refresh shipping.
     * Use CheckoutDTO with cart + paymentModuleId filled.
     */
    public function setPaymentModule(CheckoutDTO $dto): void
    {
        $this->cartSelectionService->setPaymentModule($dto);
    }

    /**
     * Force shipping recalculation for a given cart.
     * Useful after a sequence of changes when you want an explicit refresh.
     */
    public function recalculatePostage(Cart $cart): void
    {
        $this->postageHandler->handlePostageOnCart($cart);
    }

    /**
     * Reset all delivery data for a given cart.
     */
    public function reset(): void
    {
        $this->cartRetriever->fromSession()
            ?->setDeliveryModuleId(null)
            ?->setAddressDeliveryId(null)
            ?->setAddressInvoiceId(null)
            ?->setDeliveryModuleId(null)
            ?->setPaymentModuleId(null)
            ?->setPostage(null)
            ?->setPostageTax(null)
            ?->setPostageTaxRuleTitle(null)
            ->save();
    }

    /**
     * Front helper: get cart from session (can be null).
     */
    public function getCartFromSession(): ?Cart
    {
        return $this->cartRetriever->fromSession();
    }

    /**
     * Front helper: get or create a cart for the given customer.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getOrCreateForCustomer(Customer $customer): Cart
    {
        return $this->cartRetriever->fromSessionOrCreateNew($customer);
    }

    /**
     * Front helper: get or create a cart from the current session.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getOrCreateFromSession(): Cart
    {
        return $this->cartRetriever->fromSessionOrCreateNew();
    }

    /**
     * Front helper: quick accessors for common cart fields.
     */
    public function getDeliveryAddressId(): ?int
    {
        return $this->cartRetriever->fromSession()?->getAddressDeliveryId();
    }

    public function getInvoiceAddressId(): ?int
    {
        return $this->cartRetriever->fromSession()?->getAddressInvoiceId();
    }

    public function getDeliveryModuleId(): ?int
    {
        return $this->cartRetriever->fromSession()?->getDeliveryModuleId();
    }

    public function getPaymentModuleId(): ?int
    {
        return $this->cartRetriever->fromSession()?->getPaymentModuleId();
    }
}

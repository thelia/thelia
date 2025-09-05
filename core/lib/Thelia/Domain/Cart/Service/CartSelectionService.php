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

namespace Thelia\Domain\Cart\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\EventBuilder\CartCheckoutEventBuilder;
use Thelia\Domain\Shipping\Service\PostageHandler;

class CartSelectionService
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected CartCheckoutEventBuilder $cartCheckoutEventBuilder,
        protected PostageHandler $postageHandler,
    ) {
    }

    public function setDeliveryModule(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_MODULE);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    public function setDeliveryAddress(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_ADDRESS);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    public function setInvoiceAddress(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_INVOICE_ADDRESS);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    public function setPaymentModule(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_PAYMENT_MODULE);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }
}

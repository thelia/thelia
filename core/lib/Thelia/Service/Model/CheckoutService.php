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

namespace Thelia\Service\Model;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\Checkout\EmptyCartException;
use Thelia\Exception\Checkout\InvalidDeliveryException;
use Thelia\Exception\Checkout\InvalidPaymentException;
use Thelia\Exception\Checkout\MissingAddressException;
use Thelia\Model\Order;

class CheckoutService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CartService $cartService,
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

        $cart = $this->cartService->getCart();

        $newOrder = (new Order())
            ->setDeliveryOrderAddressId($deliveryAddressId)
            ->setInvoiceOrderAddressId($invoiceAddressId)
            ->setPaymentModuleId($paymentModuleId)
            ->setDeliveryModuleId($deliveryModuleId)
            ->setPostage((float) $cart->getPostage() + (float) $cart->getPostageTax())
            ->setPostageTax($cart->getPostageTax())
            ->setPostageTaxRuleTitle($cart->getPostageTaxRuleTitle());

        $orderEvent = (new OrderEvent($newOrder));

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        $placedOrder = $orderEvent->getPlacedOrder();

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_CART_CLEAR);

        if (null !== $placedOrder && null !== $placedOrder->getId()) {
            if ($orderEvent->hasResponse()) {
                return $orderEvent->getResponse();
            }
        }

        return null;
    }

    public function resetCheckout(): void
    {
        $cart = $this->cartService->getCart();
        $cart->setDeliveryModuleId(null)
            ->setAddressDeliveryId(null)
            ->setAddressInvoiceId(null)
            ->setDeliveryModuleId(null)
            ->setPaymentModuleId(null)
            ->save();

        $this->cartService->clearCartPostage();
    }

    /**
     * @throws EmptyCartException
     * @throws MissingAddressException
     * @throws InvalidDeliveryException
     * @throws InvalidPaymentException|PropelException
     */
    public function validateForOrder(): void
    {
        $this->cartService->checkCartNotEmpty();
        $this->cartService->checkValidDelivery();
        $this->cartService->checkInvoiceAddress();
        $this->cartService->checkValidPayment();
    }
}

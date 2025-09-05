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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\Order;

readonly class CheckoutPaymentService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function pay(
        Cart $cart,
        int $deliveryAddressId,
        int $invoiceAddressId,
        int $deliveryModuleId,
        int $paymentModuleId,
    ): ?Response {
        $newOrder = (new Order())
            ->setDeliveryOrderAddressId($deliveryAddressId)
            ->setInvoiceOrderAddressId($invoiceAddressId)
            ->setPaymentModuleId($paymentModuleId)
            ->setDeliveryModuleId($deliveryModuleId)
            ->setPostage((float) $cart->getPostage() + (float) $cart->getPostageTax())
            ->setPostageTax($cart->getPostageTax())
            ->setPostageTaxRuleTitle($cart->getPostageTaxRuleTitle());

        $orderEvent = new OrderEvent($newOrder);

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        $placedOrder = $orderEvent->getPlacedOrder();

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_CART_CLEAR);

        if ((null !== $placedOrder->getId()) && $orderEvent->hasResponse()) {
            return $orderEvent->getResponse();
        }

        return null;
    }
}

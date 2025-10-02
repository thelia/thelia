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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Cart;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

readonly class CheckoutPaymentService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private SecurityContext $securityContext,
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
            ->setPostageTaxRuleTitle($cart->getPostageTaxRuleTitle())
            ->setCustomerId($cart->getCustomerId())
            ->setCartId($cart->getId());

        $orderEvent = new OrderEvent($newOrder);

        $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        $placedOrder = $orderEvent->getPlacedOrder();

        if ((null !== $placedOrder->getId()) && $orderEvent->hasResponse()) {
            return $orderEvent->getResponse();
        }

        return null;
    }

    /**
     * @throws PropelException|\InvalidArgumentException
     */
    public function cancel(int $orderId): Order
    {
        $failedOrder = OrderQuery::create()->findPk($orderId);

        if (null === $failedOrder) {
            throw new \InvalidArgumentException('Order not found');
        }

        $customer = $this->securityContext->getCustomerUser();

        if ($failedOrder->getCustomerId() !== $customer->getId()) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Received failed order id does not belong to the current customer'));
        }

        $failedOrder->setCancelled();

        return $failedOrder;
    }
}

<?php

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

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;

class CheckoutService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CartService              $cartService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function pay(
        int $deliveryAddressId,
        int $invoiceAddressId,
        int $deliveryModuleId,
        int $paymentModuleId,

    ): ?Response
    {
        $this->checkValidDelivery();
        $this->checkValidInvoice();

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

    /**
     * @throws Exception
     */
    public function checkValidDelivery(): void
    {
        $cart = $this->cartService->getCart();
        $deliveryAddress = AddressQuery::create()->findPk($cart->getAddressDeliveryId());
        $deliveryModule = ModuleQuery::create()->findPk($cart->getDeliveryModuleId());

        if (!$deliveryAddress || !$deliveryModule) {
            throw new Exception(Translator::getInstance()->trans('Invalid delivery address or module.'));
        }
    }

    /**
     * @throws Exception
     */
    public function checkValidInvoice(): void
    {
        $cart = $this->cartService->getCart();
        $invoiceAddress = AddressQuery::create()->findPk($cart->getAddressInvoiceId());
        $invoiceModule = ModuleQuery::create()->findPk($cart->getPaymentModuleId());

        if (!$invoiceAddress || !$invoiceModule) {
            throw new Exception(Translator::getInstance()->trans('Invalid invoice address or module.'));
        }
    }
}

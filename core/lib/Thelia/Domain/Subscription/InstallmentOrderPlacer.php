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

namespace Thelia\Domain\Subscription;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Shipping\Service\DeliveryPostageQuerier;
use Thelia\Domain\Subscription\Exception\InstallmentNotPlaceableException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrderQuery;

/**
 * Places the order of an installment through the core placement (OrderFacade::createOrder()),
 * the one every order goes through, so that its lines, taxes, stock and reference are those of
 * any other order. No session and no request are read.
 *
 * The order is built from a cart of its own, made for it and ordered once: the sale element
 * of the subscription in its quantity at the frozen unit price, in the currency and the
 * language of the subscription, with the carrier of the subscription and the postage that
 * carrier quotes today. The addresses are copies of those of the last order of the
 * subscription, so that each order keeps its own, as the checkout does.
 */
final readonly class InstallmentOrderPlacer
{
    public function __construct(
        private OrderFacade $orderFacade,
        private DeliveryPostageQuerier $deliveryPostageQuerier,
        private EventDispatcherInterface $dispatcher,
        private InstallmentPlacementContext $placementContext,
    ) {
    }

    /**
     * @throws InstallmentNotPlaceableException when something the order needs is gone
     */
    public function place(Subscription $subscription, ConnectionInterface $connection): Order
    {
        $saleElements = null === $subscription->getProductSaleElementsId() ? null : ProductSaleElementsQuery::create()->findPk($subscription->getProductSaleElementsId(), $connection);
        $customer = CustomerQuery::create()->findPk($subscription->getCustomerId(), $connection);
        $currency = CurrencyQuery::create()->findPk($subscription->getCurrencyId(), $connection);
        $lang = LangQuery::create()->findPk($subscription->getLangId(), $connection);
        $deliveryModule = ModuleQuery::create()->findPk($subscription->getDeliveryModuleId(), $connection);
        $previousOrder = $this->previousOrderOf($subscription, $connection);

        if (null === $saleElements || null === $customer || null === $currency || null === $lang) {
            throw new InstallmentNotPlaceableException('The sale element, the customer, the currency or the language of the subscription is gone.');
        }

        if (null === $deliveryModule) {
            throw new InstallmentNotPlaceableException('The carrier of the subscription is no longer installed.');
        }

        if (null === $previousOrder) {
            throw new InstallmentNotPlaceableException('The subscription has no previous order to take the addresses from.');
        }

        $deliveryAddress = $this->copyOf((int) $previousOrder->getDeliveryOrderAddressId(), $connection);
        $invoiceAddress = $this->copyOf((int) $previousOrder->getInvoiceOrderAddressId(), $connection);

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken('subscription-'.$subscription->getId().'-'.bin2hex(random_bytes(8)))
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($subscription->getPaymentModuleId())
            ->setCustomerPaymentMethodId($subscription->getCustomerPaymentMethodId());
        $cart->save($connection);

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($saleElements->getProductId())
            ->setProductSaleElementsId($saleElements->getId())
            ->setQuantity((float) $subscription->getQuantity())
            ->setPrice($subscription->getUnitPrice())
            ->setPromoPrice($subscription->getUnitPrice())
            ->setPromo(0)
            ->setSubscriptionPlanId($subscription->getSubscriptionPlanId())
            ->save($connection);

        $cart->clearCartItems();

        $postage = $this->postageOf($deliveryModule, $cart, $deliveryAddress);

        $cart
            ->setPostage((string) $postage->getAmount())
            ->setPostageTax((string) $postage->getAmountTax())
            ->setPostageTaxRuleTitle($postage->getTaxRuleTitle())
            ->save($connection);

        $sessionOrder = (new Order())
            ->setCustomerId($customer->getId())
            ->setDeliveryOrderAddressId($deliveryAddress->getId())
            ->setInvoiceOrderAddressId($invoiceAddress->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($subscription->getPaymentModuleId())
            ->setPostage((string) $postage->getAmount())
            ->setPostageTax((string) $postage->getAmountTax())
            ->setPostageTaxRuleTitle($postage->getTaxRuleTitle());

        return $this->placementContext->during(fn (): Order => $this->orderFacade->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $currency,
            $lang,
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            // Nobody is at the keyboard: there is no consent answer to freeze.
            recordConsentAnswers: false,
        ));
    }

    /**
     * The order of the latest installment placed, the initial order before any: the
     * addresses a customer changed at a later order are the ones the next one goes to.
     */
    private function previousOrderOf(Subscription $subscription, ConnectionInterface $connection): ?Order
    {
        $latestInstallment = SubscriptionOrderQuery::create()
            ->filterBySubscriptionId($subscription->getId())
            ->filterByOrderId(null, Criteria::ISNOTNULL)
            ->orderByInstallmentNumber(Criteria::DESC)
            ->findOne($connection);

        $orderId = $latestInstallment?->getOrderId() ?? $subscription->getInitialOrderId();

        return null === $orderId ? null : OrderQuery::create()->findPk($orderId, $connection);
    }

    private function copyOf(int $orderAddressId, ConnectionInterface $connection): OrderAddress
    {
        $address = OrderAddressQuery::create()->findPk($orderAddressId, $connection);

        if (null === $address) {
            throw new InstallmentNotPlaceableException('The previous order of the subscription has lost its addresses.');
        }

        $copy = $address->copy();
        $copy->save($connection);

        return $copy;
    }

    private function postageOf(Module $deliveryModule, Cart $cart, OrderAddress $deliveryAddress): OrderPostage
    {
        $quote = $this->deliveryPostageQuerier->query($deliveryModule, $cart, null, $deliveryAddress->getCountry(), $deliveryAddress->getState());

        if (true !== $quote['valid'] || !$quote['postage'] instanceof OrderPostage) {
            throw new InstallmentNotPlaceableException(\sprintf('The carrier %s does not deliver this installment.', (string) $deliveryModule->getCode()));
        }

        return $quote['postage'];
    }
}

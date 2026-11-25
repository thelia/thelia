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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteRevision;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * What the tests of the cart of a quote share: a buyer in the session, a carrier that would quote
 * 99 if it was asked, a payment module that answers nothing, and the cart of a quote as the
 * shop and the buyer agreed it (12 of a product at 90 for a catalog price of 100, 30 off with taxes,
 * 15 of postage without taxes, 20 % VAT).
 */
abstract class QuoteCartTestCase extends ActionIntegrationPromotionTestCase
{
    protected const string CARRIER_QUOTE = '99.000000';

    protected Customer $buyer;

    protected Quote $quote;

    protected CartItem $quoteLine;

    private ?TaxRule $vatRule = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($this->buyer);

        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->vatRule()->getId());
        ConfigQuery::write('quote_mode', 'alongside');
        ConfigQuery::resetCache();

        // The carrier the shop would ask for the postage of an ordinary cart.
        $this->dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage((float) self::CARRIER_QUOTE, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );

        // The payment module would answer with a page of its own: only the placement matters.
        $this->dispatcher->addListener(
            TheliaEvents::MODULE_PAY,
            static fn (OrderPaymentEvent $event) => $event->stopPropagation(),
            512,
        );
    }

    /**
     * 20 % VAT in the default country of the shop, and nowhere else.
     */
    protected function vatRule(): TaxRule
    {
        if (null === $this->vatRule) {
            $this->vatRule = $this->factory->taxRule(['isDefault' => false]);
            $tax = $this->factory->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT 20']);
            (new TaxRuleCountry())
                ->setTaxRuleId($this->vatRule->getId())
                ->setCountryId(Country::getDefaultCountry()->getId())
                ->setTaxId($tax->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
        }

        return $this->vatRule;
    }

    /**
     * A shop where the customers are priced apart: the group of the buyer takes 15 % off the catalog,
     * so a line at 90 for a catalog price of 100 is a price the shop would write again as 85.
     */
    protected function priceTheBuyerApart(): void
    {
        $this->factory->assignCustomerGroup($this->buyer, $this->factory->customerGroup('pro-quote', 15.0));
        CustomerTableMap::clearInstancePool();
        CartTableMap::clearInstancePool();
        $this->getService('services_resetter')->reset();
    }

    /**
     * The cart of a quote accepted at its revision 2, with its address, its carrier and its payment
     * module, the postage the revision carries, and held by the session.
     */
    protected function quoteCart(string $discount = '30.000000', string $postage = '15.000000', ?Customer $buyer = null): Cart
    {
        $buyer ??= $this->buyer;
        $product = $this->product(['basePrice' => 100.0]);
        $currency = $this->factory->currency();
        $country = Country::getDefaultCountry();
        $connection = $this->getPropelConnection();

        $this->quote = (new Quote())
            ->setRef('QUO'.str_pad((string) random_int(1, 99_999_999), 8, '0', \STR_PAD_LEFT))
            ->setCustomerId($buyer->getId())
            ->setState('accepted')
            ->setCurrentRevision(2)
            ->setCurrencyId($currency->getId());
        $this->quote->save($connection);

        (new QuoteRevision())->setQuoteId($this->quote->getId())->setNumber(1)->setAuthor('customer')->setState('sent')->save($connection);
        $revision = (new QuoteRevision())
            ->setQuoteId($this->quote->getId())
            ->setNumber(2)
            ->setAuthor('admin')
            ->setState('sent')
            ->setDiscount($discount)
            ->setPostage($postage);
        $revision->save($connection);

        $pse = $this->defaultPseFor($product);
        $quoteItem = (new QuoteItem())
            ->setQuoteRevisionId($revision->getId())
            ->setPosition(1)
            ->setProductId($product->getId())
            ->setProductSaleElementsId($pse->getId())
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef((string) $pse->getRef())
            ->setTitle('Quoted product')
            ->setQuantity(12.0)
            ->setCatalogPrice('100.000000')
            ->setPrice('90.000000')
            ->setTaxRuleId($this->vatRule()->getId());
        $quoteItem->save($connection);

        $cart = $this->factory->cart($buyer);
        $cart
            ->setQuoteId($this->quote->getId())
            ->setQuoteRevision(2)
            ->setDiscount($discount)
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setAddressDeliveryId($this->factory->cartAddress($this->factory->address($buyer, $country), $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress($this->factory->address($buyer))->getId())
            ->save($connection);

        $this->quoteLine = $this->factory->cartItem($cart, $product, overrides: ['quantity' => 12.0, 'price' => '90.000000', 'promoPrice' => '90.000000']);
        $this->quoteLine->setQuoteItemId($quoteItem->getId())->save($connection);

        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
        $this->getService(PostageHandler::class)->handlePostageOnCart($cart);

        return $this->reloaded($cart);
    }

    /**
     * An ordinary cart of the buyer: 12 of a product at 90 for a catalog price of 100, written by hand
     * (the line is no line of a quote), delivered and paid like the cart of a quote.
     */
    protected function ordinaryCart(?Customer $buyer = null): Cart
    {
        $buyer ??= $this->buyer;
        $product = $this->product(['basePrice' => 100.0]);
        $cart = $this->factory->cart($buyer);
        $cart
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setAddressDeliveryId($this->factory->cartAddress($this->factory->address($buyer))->getId())
            ->setAddressInvoiceId($this->factory->cartAddress($this->factory->address($buyer))->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());
        $this->factory->cartItem($cart, $product, overrides: ['quantity' => 12.0, 'price' => '90.000000', 'promoPrice' => '90.000000']);

        return $this->reloaded($cart);
    }

    protected function reloaded(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();

        return CartQuery::create()->findPk($cart->getId()) ?? throw new \LogicException('The cart is gone.');
    }

    protected function lineOf(Cart $cart): CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(0)->findOne() ?? throw new \LogicException('The cart has no line.');
    }

    protected function pay(Cart $cart, ?Currency $currency = null): Order
    {
        $cart = $this->reloaded($cart);

        return $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) $cart->getPaymentModuleId(),
            $currency,
        ))->placedOrder;
    }

    /**
     * The condition of a promotion that holds for a cart with at least one line.
     */
    protected function atLeastOneLine(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }

    /**
     * A promotion that applies on its own to a cart with at least one line: 10 % off.
     */
    protected function tenPercentOffPromotion(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
    }
}

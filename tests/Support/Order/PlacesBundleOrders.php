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

namespace Thelia\Tests\Support\Order;

use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;

/**
 * Carts holding packs, placed through the OrderFacade the way the checkout does, for a test
 * that extends {@see \Thelia\Test\ActionIntegrationTestCase}.
 *
 * The prices on the cart lines are the ones the cart settles; the order copies them, so the
 * values chosen here only have to be told apart.
 */
trait PlacesBundleOrders
{
    private ?Country $buyerCountry = null;

    /** @var list<int> the order addresses placeOrder() wrote, for a test that commits and cleans up by hand */
    private array $orderAddressIdsOfPlacements = [];

    /**
     * @return array{0: Customer, 1: Currency, 2: Lang}
     */
    private function aBuyer(): array
    {
        return [
            $this->factory->customer($this->factory->customerTitle()),
            $this->factory->currency(),
            $this->factory->lang(),
        ];
    }

    private function aCart(Customer $customer, Currency $currency): Cart
    {
        return $this->factory->cart($customer, ['currency' => $currency]);
    }

    /**
     * @param array<int, string> $quantityByItemId the quantity of each bundle item, by item id
     */
    private function addBundleLine(Cart $cart, Product $bundle, array $quantityByItemId, float $quantity = 1.0, string $price = '600.000000', string $promoPrice = '540.000000'): CartItem
    {
        $lines = [];

        foreach ($quantityByItemId as $itemId => $itemQuantity) {
            $lines[] = ['bundleItemId' => $itemId, 'quantity' => $itemQuantity];
        }

        // Written whole, composition included: a line of a bundle product that says nothing
        // of its composition is refused when it is saved.
        $cartItem = (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($bundle->getId())
            ->setProductSaleElementsId($this->saleElementsIdOf($bundle))
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setPromoPrice($promoPrice)
            ->setPromo($price === $promoPrice ? 0 : 1)
            ->setIsOffered(0)
            ->setBundleComposition(BundleComposition::fromArray($lines)->toCanonicalJson());
        $cartItem->save();

        return $cartItem;
    }

    private function addSimpleLine(Cart $cart, Product $product, float $quantity = 1.0, string $price = '10.000000'): CartItem
    {
        return $this->factory->cartItem($cart, $product, null, ['quantity' => $quantity, 'price' => $price, 'promoPrice' => $price]);
    }

    /**
     * A rule that taxes at the given percentage in the country the order is delivered to.
     */
    private function taxRuleTaxingAt(string $percent): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->buyerCountry()->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save();

        return $taxRule;
    }

    private function buyerCountry(): Country
    {
        return $this->buyerCountry ??= $this->factory->country();
    }

    private function placeOrder(Cart $cart, Customer $customer, Currency $currency, Lang $lang): Order
    {
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($deliveryModule);
        self::assertNotNull($paymentModule);

        $deliveryAddressId = (int) $this->factory->orderAddress($this->buyerCountry())->getId();
        $invoiceAddressId = (int) $this->factory->orderAddress($this->buyerCountry())->getId();
        $this->orderAddressIdsOfPlacements = [...$this->orderAddressIdsOfPlacements, $deliveryAddressId, $invoiceAddressId];

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($deliveryAddressId)
            ->setInvoiceOrderAddressId($invoiceAddressId)
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        return $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $currency,
            $lang,
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            recordConsentAnswers: false,
        );
    }

    /**
     * @return list<OrderProduct> the lines of the order, in the order they were written
     */
    private function linesOf(Order $order): array
    {
        return OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find()->getData();
    }

    private function stockOfProduct(Product $product): float
    {
        return (float) ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne()?->getQuantity();
    }

    private function saleElementsIdOf(Product $product): int
    {
        return (int) ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne()?->getId();
    }
}

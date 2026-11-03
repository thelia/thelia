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

namespace Thelia\Domain\Taxation\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\GiftWrappingQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\State;

/**
 * The VAT a cart would have carried had it not been exempted.
 *
 * An exempt order writes no tax line at all, so the figure an invoice has to
 * state - the VAT the buyer accounts for himself - cannot be read back from the
 * order afterwards. It is computed here, once, while the cart is still around,
 * and frozen on the invoice address.
 *
 * Everything goes through the factory rather than through the cart and its
 * lines: their calculators resolve to the exempt one for this very cart, which
 * would answer zero to every question asked here.
 */
readonly class ExemptedVatCalculator
{
    public function __construct(
        private TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private GiftWrappingProvider $giftWrappingProvider,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function forCart(
        Cart $cart,
        Country $country,
        ?State $state,
        float $postageVat,
    ): float {
        $vat = 0.0;
        $untaxedTotal = 0.0;
        $cartItems = $cart->getCartItems();
        $productsById = $this->productsOf($cartItems);

        foreach ($cartItems as $cartItem) {
            $untaxedPrice = 1 === (int) $cartItem->getPromo()
                ? (float) $cartItem->getPromoPrice()
                : (float) $cartItem->getPrice();

            $taxedPrice = $this->taxCalculatorFactory->createTaxCalculator()
                ->load($productsById[$cartItem->getProductId()], $country, $state)
                ->getTaxedPrice($untaxedPrice);

            $untaxedLineTotal = $this->lineTotal($untaxedPrice, (float) $cartItem->getQuantity());
            $vat += $this->lineTotal($taxedPrice, (float) $cartItem->getQuantity()) - $untaxedLineTotal;
            $untaxedTotal += $untaxedLineTotal;
        }

        $discount = min((float) $cart->getDiscount(), $untaxedTotal);

        if ($discount > 0.0) {
            $vat -= $vat * $discount / $untaxedTotal;
        }

        $vat += $postageVat + $this->giftWrappingVat($cart, $country, $state);

        return round(max(0.0, $vat), 2);
    }

    /**
     * The same figure read from the lines an order carries now, for an order
     * whose lines were edited after the cart that placed it stopped being the
     * truth. A line whose product or gift wrapping no longer exists adds no VAT.
     *
     * @throws PropelException
     */
    public function forOrder(
        Order $order,
        Country $country,
        ?State $state,
        float $postageVat,
    ): float {
        $vat = 0.0;
        $untaxedTotal = 0.0;
        $wrappingVat = 0.0;

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->find() as $line) {
            if ($line->isServiceLine()) {
                $wrappingVat += $this->wrappingVatOf($line, $country, $state);

                continue;
            }

            $product = ProductQuery::create()->findOneByRef($line->getProductRef());

            if (null === $product) {
                continue;
            }

            $untaxedPrice = (float) ($line->getWasInPromo() ? $line->getPromoPrice() : $line->getPrice());
            $taxedPrice = $this->taxCalculatorFactory->createTaxCalculator()
                ->load($product, $country, $state)
                ->getTaxedPrice($untaxedPrice);

            $untaxedLineTotal = $this->lineTotal($untaxedPrice, (float) $line->getQuantity());
            $vat += $this->lineTotal($taxedPrice, (float) $line->getQuantity()) - $untaxedLineTotal;
            $untaxedTotal += $untaxedLineTotal;
        }

        $discount = min((float) $order->getDiscount(), $untaxedTotal);

        if ($discount > 0.0) {
            $vat -= $vat * $discount / $untaxedTotal;
        }

        return round(max(0.0, $vat + $postageVat + $wrappingVat), 2);
    }

    private function wrappingVatOf(OrderProduct $line, Country $country, ?State $state): float
    {
        $giftWrapping = GiftWrappingQuery::create()->findOneByCode($line->getProductRef());

        if (null === $giftWrapping) {
            return 0.0;
        }

        return $this->giftWrappingProvider->taxedPrice($giftWrapping, $country, $state)
            - round((float) $giftWrapping->getPrice(), 2);
    }

    private function giftWrappingVat(Cart $cart, Country $country, ?State $state): float
    {
        $giftWrapping = $this->giftWrappingProvider->findActive(
            null === $cart->getGiftWrappingId() ? null : (int) $cart->getGiftWrappingId()
        );

        if (null === $giftWrapping) {
            return 0.0;
        }

        return $this->giftWrappingProvider->taxedPrice($giftWrapping, $country, $state)
            - round((float) $giftWrapping->getPrice(), 2);
    }

    private function lineTotal(float $unitPrice, float $quantity): float
    {
        if (ConfigQuery::isRoundingModeRoundingOfSums()) {
            return round($unitPrice * $quantity, 2);
        }

        return round($unitPrice, 2) * $quantity;
    }

    /**
     * @param iterable<CartItem> $cartItems
     *
     * @return array<int, Product>
     */
    private function productsOf(iterable $cartItems): array
    {
        $productIds = [];

        foreach ($cartItems as $cartItem) {
            $productIds[] = $cartItem->getProductId();
        }

        $productsById = [];

        foreach (ProductQuery::create()->filterById(array_unique($productIds))->find() as $product) {
            $productsById[$product->getId()] = $product;
        }

        return $productsById;
    }
}

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
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
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
        $cartItems = $cart->getCartItems();
        $productsById = $this->productsOf($cartItems);

        foreach ($cartItems as $cartItem) {
            $untaxedPrice = 1 === (int) $cartItem->getPromo()
                ? (float) $cartItem->getPromoPrice()
                : (float) $cartItem->getPrice();

            $taxedPrice = $this->taxCalculatorFactory->createTaxCalculator()
                ->load($productsById[$cartItem->getProductId()], $country, $state)
                ->getTaxedPrice($untaxedPrice);

            $vat += $this->lineTotal($taxedPrice, (float) $cartItem->getQuantity()) - $this->lineTotal($untaxedPrice, (float) $cartItem->getQuantity());
        }

        $discount = (float) $cart->getDiscount();

        if (0.0 !== $discount) {
            $vat -= $discount * ($this->taxCalculatorFactory->createTaxCalculator()
                ->computeCartTaxFactor($cart, $country, $state) - 1);
        }

        $vat += $postageVat;

        return round(max(0.0, $vat), 2);
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

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

use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Pricing\BundleEffectivePriceSource;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Writes on cart lines what a customer pays, as the customer price context resolves
 * it: one call for all the lines, whatever the number of sale elements.
 *
 * The line is the proof of the price the shop agreed to, so the price is written down
 * here and not worked out again later, when the rule, the operation or the group
 * discount may well be over. `price` and `promo_price` are DECIMAL(16,6) columns:
 * both are rounded to six decimals here, and only here, and a line is compared on the
 * rounded values, so a pricing that changes nothing writes nothing.
 *
 * A sale element the context cannot price (no price in the default currency) is left
 * alone: its line keeps the price it had, and nothing is raised.
 *
 * A line of a bundle priced by its components is priced from the composition it holds, not
 * from the default one of its sale element: the components the customer chose, less the
 * discount of the bundle, then the discounts of the group and of the customer. A composition
 * the bundle can no longer hold leaves the line alone, at its last price: the order refuses it.
 */
final readonly class CartLinePricer
{
    public function __construct(
        private CustomerPriceContextInterface $customerPriceContext,
        private ?BundleEffectivePriceSource $bundleEffectivePriceSource = null,
    ) {
    }

    /**
     * Prices the lines in a single resolution and returns those whose price fields were
     * written, to be saved by the caller. A new line is always written when it has a
     * price, so an empty answer for a new line means it could not be priced.
     *
     * @param iterable<CartItem> $lines
     *
     * @return list<CartItem>
     */
    public function price(iterable $lines, Currency $currency, ?Customer $customer): array
    {
        $written = [];

        foreach ($this->resolve($lines, $currency, $customer) as [$line, $price]) {
            if ($this->differs($line, $price)) {
                $this->write($line, $price);
                $written[] = $line;
            }
        }

        return $written;
    }

    /**
     * Brings the lines of a cart in line with the price its customer is owed, in the
     * given currency, and returns the lines whose price moved (saved). The lines a
     * promotion offered are left alone: they belong to the promotion that placed them,
     * which set their price.
     *
     * With $bundleLinesOnly, only the lines priced by the components of their bundle are
     * repriced: the others keep the price they hold.
     *
     * @return list<CartItem>
     */
    public function reprice(Cart $cart, Currency $currency, ?Customer $customer, bool $bundleLinesOnly = false): array
    {
        $moved = [];

        foreach ($this->resolve($this->payableLines($cart), $currency, $customer, $bundleLinesOnly) as [$line, $price]) {
            if ($this->differs($line, $price)) {
                $this->write($line, $price);
                $moved[] = $line;
            }
        }

        foreach ($moved as $line) {
            $line->save();
        }

        return $moved;
    }

    /**
     * The lines of a cart whose price is not the one its customer is owed, in the given
     * currency. Nothing is written, not even on the objects: this is the question
     * {@see reprice()} answers by doing.
     *
     * @return list<CartItem>
     */
    public function staleLinesOf(Cart $cart, Currency $currency, ?Customer $customer): array
    {
        $stale = [];

        foreach ($this->resolve($this->payableLines($cart), $currency, $customer) as [$line, $price]) {
            if ($this->differs($line, $price)) {
                $stale[] = $line;
            }
        }

        return $stale;
    }

    /**
     * @return list<CartItem>
     */
    private function payableLines(Cart $cart): array
    {
        $lines = [];

        foreach ($cart->getCartItems() as $line) {
            if (1 !== (int) $line->getIsOffered()) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param iterable<CartItem> $lines
     * @param bool               $bundleLinesOnly only the lines priced by the components of their bundle
     *
     * @return list<array{CartItem, ResolvedPrice}> the lines the context could price, with their price
     */
    private function resolve(iterable $lines, Currency $currency, ?Customer $customer, bool $bundleLinesOnly = false): array
    {
        $lines = array_values([...$lines]);
        $bundlePrices = $this->bundleLinePricesOf($lines, $currency, $customer);
        $otherLines = array_filter(
            $lines,
            static fn (CartItem $line, int $index): bool => !\array_key_exists($index, $bundlePrices),
            \ARRAY_FILTER_USE_BOTH,
        );
        $quantities = [];

        foreach ($otherLines as $line) {
            // The tier of a price list is the quantity of the line the customer holds.
            $pseId = (int) $line->getProductSaleElementsId();
            $quantities[$pseId] = ($quantities[$pseId] ?? 0.0) + (float) $line->getQuantity();
        }

        $prices = $bundleLinesOnly ? [] : $this->customerPriceContext->resolve(
            array_values(array_map(static fn (CartItem $line): int => (int) $line->getProductSaleElementsId(), $otherLines)),
            $currency,
            $customer,
            array_map(static fn (float $quantity): int => (int) floor($quantity), $quantities),
        );

        $resolved = [];

        foreach ($lines as $index => $line) {
            $price = \array_key_exists($index, $bundlePrices)
                ? $bundlePrices[$index]
                : ($bundleLinesOnly ? null : $prices[(int) $line->getProductSaleElementsId()] ?? null);

            if (null !== $price) {
                $resolved[] = [$line, $price];
            }
        }

        return $resolved;
    }

    /**
     * The price of each line that holds the composition of a bundle priced by its components, by
     * the index of the line: null for a composition the bundle can no longer hold, absent for a
     * line that is no such bundle (a simple product, a fixed bundle), which its sale element prices.
     *
     * @param list<CartItem> $lines
     *
     * @return array<int, ResolvedPrice|null>
     */
    private function bundleLinePricesOf(array $lines, Currency $currency, ?Customer $customer): array
    {
        if (null === $this->bundleEffectivePriceSource) {
            return [];
        }

        $compositions = [];

        foreach ($lines as $index => $line) {
            if (null === $line->getProductSaleElementsId()) {
                continue;
            }

            try {
                $composition = $line->getBundleCompositionValue();
            } catch (InvalidBundleCompositionException) {
                // A composition that no longer reads is left to the order, which refuses it.
                continue;
            }

            if (null !== $composition) {
                $compositions[$index] = [(int) $line->getProductId(), (int) $line->getProductSaleElementsId(), $composition];
            }
        }

        if ([] === $compositions) {
            return [];
        }

        $effectivePrices = $this->bundleEffectivePriceSource->forLines($compositions, $currency);
        $priced = $this->customerPriceContext->resolveBundlePrices(array_filter($effectivePrices), $customer);
        $prices = [];

        foreach ($effectivePrices as $index => $effectivePrice) {
            $prices[$index] = null === $effectivePrice ? null : $priced[$index];
        }

        return $prices;
    }

    private function differs(CartItem $line, ResolvedPrice $price): bool
    {
        return $line->isNew()
            || round((float) $line->getPrice(), 6) !== round($price->untaxedPrice, 6)
            || round((float) $line->getPromoPrice(), 6) !== round($price->untaxedPromoPrice, 6)
            || (int) $line->getPromo() !== ($price->isPromo ? 1 : 0)
            || $line->getPriceListId() !== $price->pricedByPriceListId();
    }

    private function write(CartItem $line, ResolvedPrice $price): void
    {
        $line
            ->setPrice(number_format(round($price->untaxedPrice, 6), 6, '.', ''))
            ->setPromoPrice(number_format(round($price->untaxedPromoPrice, 6), 6, '.', ''))
            ->setPromo($price->isPromo ? 1 : 0)
            // The trace of a price list: once the list is turned off or deleted, it is what tells
            // the cart that its line holds a price nothing else would give it.
            ->setPriceListId($price->pricedByPriceListId());
    }
}

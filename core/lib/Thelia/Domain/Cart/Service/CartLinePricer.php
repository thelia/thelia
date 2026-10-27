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
 */
final readonly class CartLinePricer
{
    public function __construct(private CustomerPriceContextInterface $customerPriceContext)
    {
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
     * @return list<CartItem>
     */
    public function reprice(Cart $cart, Currency $currency, ?Customer $customer): array
    {
        $moved = $this->price($this->payableLines($cart), $currency, $customer);

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
     *
     * @return list<array{CartItem, ResolvedPrice}> the lines the context could price, with their price
     */
    private function resolve(iterable $lines, Currency $currency, ?Customer $customer): array
    {
        $lines = [...$lines];
        $prices = $this->customerPriceContext->resolve(
            array_map(static fn (CartItem $line): int => (int) $line->getProductSaleElementsId(), $lines),
            $currency,
            $customer,
        );

        $resolved = [];

        foreach ($lines as $line) {
            $price = $prices[(int) $line->getProductSaleElementsId()] ?? null;

            if (null !== $price) {
                $resolved[] = [$line, $price];
            }
        }

        return $resolved;
    }

    private function differs(CartItem $line, ResolvedPrice $price): bool
    {
        return $line->isNew()
            || round((float) $line->getPrice(), 6) !== round($price->untaxedPrice, 6)
            || round((float) $line->getPromoPrice(), 6) !== round($price->untaxedPromoPrice, 6)
            || (int) $line->getPromo() !== ($price->isPromo ? 1 : 0);
    }

    private function write(CartItem $line, ResolvedPrice $price): void
    {
        $line
            ->setPrice(number_format(round($price->untaxedPrice, 6), 6, '.', ''))
            ->setPromoPrice(number_format(round($price->untaxedPromoPrice, 6), 6, '.', ''))
            ->setPromo($price->isPromo ? 1 : 0);
    }
}

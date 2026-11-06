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

namespace Thelia\Domain\Cart\Discount;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Model\Cart;

/**
 * The discount lines the modules declare for a cart ({@see CartDiscountLineProviderInterface}).
 */
final readonly class CartDiscountLines
{
    /**
     * @param iterable<CartDiscountLineProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(CartDiscountLineProviderInterface::TAG)]
        private iterable $providers,
    ) {
    }

    /**
     * Whether a module may take something off a cart: the cart discount is then the core's to
     * price again at each change, whatever the promotions say.
     */
    public function haveProviders(): bool
    {
        foreach ($this->providers as $provider) {
            return true;
        }

        return false;
    }

    /**
     * The lines that take something off this cart, rounded to the cent, in the order of the providers.
     *
     * @return list<CartDiscountLine>
     */
    public function of(Cart $cart): array
    {
        $lines = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->discountLinesFor($cart) as $line) {
                $taxedAmount = round($line->taxedAmount, 2);

                if ($taxedAmount <= 0.0) {
                    continue;
                }

                if (isset($lines[$line->key])) {
                    throw new \LogicException(\sprintf('Two discount lines of the cart share the key "%s".', $line->key));
                }

                $lines[$line->key] = new CartDiscountLine($line->key, $line->label, $taxedAmount);
            }
        }

        return array_values($lines);
    }

    /**
     * What the lines take off together, tax included.
     *
     * @param list<CartDiscountLine> $lines
     */
    public static function total(array $lines): float
    {
        return round(array_sum(array_map(static fn (CartDiscountLine $line): float => $line->taxedAmount, $lines)), 2);
    }

    /**
     * What each line is granted out of a discount: the lines are served first, in their order,
     * each up to its amount, until the discount runs out. What is left goes to the promotions.
     *
     * @param list<CartDiscountLine> $lines
     *
     * @return list<float> one taxed amount per line, in the same order
     */
    public static function serve(array $lines, float $discount): array
    {
        $left = max(0.0, round($discount, 2));
        $granted = [];

        foreach ($lines as $line) {
            $amount = min($line->taxedAmount, $left);
            $granted[] = $amount;
            $left = round($left - $amount, 2);
        }

        return $granted;
    }
}

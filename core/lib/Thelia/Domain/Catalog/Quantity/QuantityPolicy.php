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

namespace Thelia\Domain\Catalog\Quantity;

use Thelia\Model\ProductSaleElements;

/**
 * Decides a quantity against the rules of its combination: a minimum, a step, the
 * decimals. Without state, session or query: it reads the combination already loaded.
 *
 * Two modes. Where the buyer asks for a quantity and can change it (a form, a cart
 * action, the front API, an import, the quick order), a quantity the rules do not
 * accept is refused with a message giving the valid ones. Where a line already in a
 * cart is read back against rules it no longer meets (a restored cart, rules changed
 * since), it is brought down to the valid multiple below it, or removed when even that
 * is below the smallest quantity sold.
 *
 * Quantities are compared as whole numbers of 10^-decimals, never as floats.
 */
final readonly class QuantityPolicy
{
    /** Beyond this, a quantity no longer fits the integer arithmetic of the rules. */
    private const LARGEST_QUANTITY = 1_000_000_000;

    /**
     * The quantity accepted, as the rules write it.
     *
     * @throws NotPositiveQuantityException when the quantity is zero or less
     * @throws QuantityRefusedException     when the rules do not accept the quantity
     * @throws UnreadableQuantityException  when the quantity is not a number or is beyond any stock
     */
    public function assertAcceptable(float $requested, ProductSaleElements|QuantityRule $rule): float
    {
        $decision = $this->normalize($requested, $rule);

        if ($decision->isAdjusted() || null === $decision->quantity) {
            throw new QuantityRefusedException($decision);
        }

        return $decision->quantity;
    }

    /**
     * The quantity brought down to the valid multiple below it, null when that multiple
     * is below the smallest quantity sold.
     *
     * @throws NotPositiveQuantityException when the quantity is zero or less
     * @throws UnreadableQuantityException  when the quantity is not a number or is beyond any stock
     */
    public function normalize(float $requested, ProductSaleElements|QuantityRule $rule): QuantityDecision
    {
        $rule = self::ruleOf($rule);

        if (is_nan($requested) || $requested > self::LARGEST_QUANTITY) {
            throw new UnreadableQuantityException((string) $requested);
        }

        // A millionth is the finest quantity stored: anything finer is nothing.
        if ($requested <= 0 || QuantityScale::micro($requested) <= 0) {
            throw new NotPositiveQuantityException($requested);
        }

        $scaled = QuantityScale::scaledDown($requested, $rule->decimals);
        $step = $rule->scaledStep();
        $kept = intdiv($scaled, $step) * $step;

        if ($kept < $rule->smallestScaledQuantity()) {
            return new QuantityDecision($requested, null, QuantityAdjustment::BelowMinimum, $rule);
        }

        $adjustment = match (true) {
            !QuantityScale::fits($requested, $rule->decimals) => QuantityAdjustment::TooManyDecimals,
            $kept !== $scaled => QuantityAdjustment::OffStep,
            default => null,
        };

        return new QuantityDecision($requested, QuantityScale::unscaled($kept, $rule->decimals), $adjustment, $rule);
    }

    /**
     * A stock written by hand, an import or the admin API: neither a minimum nor a step
     * holds there, zero and below are a stock too, but a stock with more decimals than
     * the combination is sold with (12.5 of a combination sold by the piece) is a unit
     * the combination does not sell in.
     *
     * @throws QuantityRefusedException    when the stock carries more decimals than the rule
     * @throws UnreadableQuantityException when the stock is not a number or is beyond any stock
     */
    public function assertStockFits(float $stock, ProductSaleElements|QuantityRule $rule): float
    {
        $rule = self::ruleOf($rule);

        if (is_nan($stock) || abs($stock) > self::LARGEST_QUANTITY) {
            throw new UnreadableQuantityException((string) $stock);
        }

        if (!QuantityScale::fits($stock, $rule->decimals)) {
            throw new QuantityRefusedException(new QuantityDecision($stock, null, QuantityAdjustment::TooManyDecimals, $rule));
        }

        return $stock;
    }

    /**
     * Reads a typed quantity, "1,25" as well as "1.25". A separator followed by three
     * digits after one to three others ("1.250", "1,250") reads as a thousand two hundred
     * and fifty in one language and as one and a quarter in another: it is refused unless
     * the combination sells with three decimals, rather than guessed.
     *
     * @throws UnreadableQuantityException
     */
    public function parse(string $input, ProductSaleElements|QuantityRule $rule): float
    {
        $rule = self::ruleOf($rule);
        $trimmed = (string) preg_replace('/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', $input);

        if (1 !== preg_match('/^([+-]?)(\d*)(?:[.,](\d+))?$/', $trimmed, $parts) || ('' === $parts[2] && '' === ($parts[3] ?? ''))) {
            throw new UnreadableQuantityException($input);
        }

        $integerPart = $parts[2];
        $fractionPart = $parts[3] ?? '';

        if (3 === \strlen($fractionPart)
            && $rule->decimals < QuantityScale::MAXIMUM_DECIMALS
            && 1 === preg_match('/^[1-9]\d{0,2}$/', $integerPart)) {
            throw new UnreadableQuantityException($input);
        }

        return (float) ($parts[1].('' === $integerPart ? '0' : $integerPart).('' === $fractionPart ? '' : '.'.$fractionPart));
    }

    private static function ruleOf(ProductSaleElements|QuantityRule $rule): QuantityRule
    {
        return $rule instanceof QuantityRule ? $rule : QuantityRule::fromProductSaleElements($rule);
    }
}

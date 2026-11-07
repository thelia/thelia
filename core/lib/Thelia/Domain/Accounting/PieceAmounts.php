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

namespace Thelia\Domain\Accounting;

/**
 * The arithmetic the pieces of the journal share: whole cents, split in proportion, sorted
 * by rate, converted to the shop currency.
 */
final class PieceAmounts
{
    /**
     * The rate the chart files an amount and its tax under (AccountingChart::rateFor()); the
     * rate the two amounts make when the chart has none that fits, for the refusal to name it.
     */
    public static function rateOf(AccountingChart $chart, float $amount, float $tax): string
    {
        return $chart->rateFor($amount, $tax) ?? AccountingChart::rateKey($amount > 0 ? $tax / $amount * 100 : 0.0);
    }

    /**
     * Splits whole cents in proportion of the weights; the cents the rounding leaves go to
     * the largest remainders, so the shares add up to the total exactly. Nothing to weigh
     * puts everything on the first key.
     *
     * @param array<string, float> $weights
     *
     * @return array<string, int>
     */
    public static function allocate(int $cents, array $weights): array
    {
        if (0 === $cents) {
            return [];
        }

        $sum = array_sum(array_map('abs', $weights));

        if ([] === $weights || $sum <= 0.0) {
            return [array_key_first($weights) ?? AccountingChart::rateKey(0.0) => $cents];
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $cents * abs($weight) / $sum;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }

        arsort($remainders);

        foreach (array_keys($remainders) as $key) {
            if (array_sum($shares) >= $cents) {
                break;
            }

            ++$shares[$key];
        }

        return array_filter($shares, static fn (int $share): bool => 0 !== $share);
    }

    /**
     * @param array<string, int> $amounts
     *
     * @return array<string, int> the highest rate first
     */
    public static function byRate(array $amounts): array
    {
        uksort($amounts, static fn (string $left, string $right): int => (float) $right <=> (float) $left);

        return $amounts;
    }

    /**
     * Each line converted at the rate of the order, the cent the conversion leaves on the
     * largest credit so that the piece still balances.
     *
     * @param list<AccountingEntry> $entries
     *
     * @return list<AccountingEntry>
     */
    public static function inShopCurrency(array $entries, float $rate): array
    {
        $rate = $rate > 0 ? $rate : 1.0;
        $converted = [];

        foreach ($entries as $entry) {
            $converted[] = new AccountingEntry(
                $entry->role,
                $entry->account,
                $entry->rateKey,
                (int) round($entry->debitCents / $rate),
                (int) round($entry->creditCents / $rate),
                $entry->debitCents + $entry->creditCents,
            );
        }

        $difference = array_sum(array_map(static fn (AccountingEntry $entry): int => $entry->debitCents - $entry->creditCents, $converted));

        if (0 !== $difference) {
            // On the largest sale rather than on a tax: the tax stays the converted tax.
            $largest = null;

            foreach ([AccountingEntry::ROLE_PRODUCT, AccountingEntry::ROLE_SHIPPING, AccountingEntry::ROLE_TAX] as $role) {
                foreach ($converted as $index => $entry) {
                    if ($role === $entry->role && $entry->creditCents > 0 && (null === $largest || $entry->creditCents > $converted[$largest]->creditCents)) {
                        $largest = $index;
                    }
                }

                if (null !== $largest) {
                    break;
                }
            }

            if (null !== $largest) {
                $entry = $converted[$largest];
                $converted[$largest] = new AccountingEntry($entry->role, $entry->account, $entry->rateKey, 0, $entry->creditCents + $difference, $entry->foreignCents);
            }
        }

        return array_values(array_filter($converted, static fn (AccountingEntry $entry): bool => 0 !== $entry->debitCents || 0 !== $entry->creditCents));
    }

    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}

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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

/**
 * Reads what a `tfilters` query string selected. The selection is nested one or two levels
 * deep depending on the client (`tfilters[brand][brand][]=1` or `tfilters[feature][7][]=12`),
 * and a bounded group carries `min`/`max` keys instead of identifiers.
 */
trait SelectedValuesTrait
{
    /**
     * Every identifier checked, whatever the group, without duplicates.
     *
     * @return list<int|string>
     */
    private function flattenSelectedValues(mixed $value): array
    {
        $ids = [];

        foreach ((array) $value as $childValue) {
            foreach ((array) $childValue as $id) {
                if (\is_array($id) || $id === null || $id === '') {
                    continue;
                }

                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The groups of the selection split by mode: `checked` holds the identifiers checked per
     * group, `bounded` holds the `min`/`max` bounds per group. A group is a feature or an
     * attribute id; a group that carries both a bound and identifiers is read as bounded.
     *
     * @return array{checked: array<int|string, list<int|string>>, bounded: array<int|string, array{min?: mixed, max?: mixed}>}
     */
    private function splitSelectedValues(mixed $value): array
    {
        $checked = [];
        $bounded = [];

        foreach ((array) $value as $group => $childValue) {
            $childValue = (array) $childValue;

            if (\array_key_exists('min', $childValue) || \array_key_exists('max', $childValue)) {
                $bounds = array_filter(
                    ['min' => $childValue['min'] ?? null, 'max' => $childValue['max'] ?? null],
                    static fn (mixed $bound): bool => $bound !== null && $bound !== '' && !\is_array($bound),
                );

                if ($bounds !== []) {
                    $bounded[$group] = $bounds;
                }

                continue;
            }

            $ids = $this->flattenSelectedValues([$childValue]);

            if ($ids !== []) {
                $checked[$group] = $ids;
            }
        }

        return ['checked' => $checked, 'bounded' => $bounded];
    }
}

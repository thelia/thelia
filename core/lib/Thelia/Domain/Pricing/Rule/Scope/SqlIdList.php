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

namespace Thelia\Domain\Pricing\Rule\Scope;

/**
 * Integer ids the way they go into a SQL `IN` list: cast, distinct, and never empty -
 * an empty list would be a syntax error, so it becomes a list matching nothing.
 */
final class SqlIdList
{
    /**
     * @param iterable<int|string> $ids
     */
    public static function inList(iterable $ids): string
    {
        $distinct = [];

        foreach ($ids as $id) {
            $distinct[(int) $id] = true;
        }

        if ([] === $distinct) {
            return '(-1)';
        }

        return '('.implode(', ', array_keys($distinct)).')';
    }
}

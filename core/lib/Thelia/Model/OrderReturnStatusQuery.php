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

namespace Thelia\Model;

use Thelia\Model\Base\OrderReturnStatusQuery as BaseOrderReturnStatusQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'order_return_status' table.
 */
class OrderReturnStatusQuery extends BaseOrderReturnStatusQuery
{
    /**
     * @var array<string, OrderReturnStatus|null>
     */
    protected static array $statusByCodeCache = [];

    /**
     * Drop the memoized lookups.
     *
     * The cache lives as long as the PHP process, which is a request for the
     * web front and days for a Messenger worker: without this, a status the
     * merchant renames stays answered under its old code until the worker is
     * restarted. Under test it also outlives the transaction rollback, and
     * hands the next test a row that no longer exists.
     */
    public static function resetCache(): void
    {
        self::$statusByCodeCache = [];
    }

    /**
     * Return the status bearing the given code, or null when none exists.
     */
    public function findOneByCodeCached(string $code): ?OrderReturnStatus
    {
        if (!\array_key_exists($code, self::$statusByCodeCache)) {
            self::$statusByCodeCache[$code] = self::create()->filterByCode($code)->findOne();
        }

        return self::$statusByCodeCache[$code];
    }

    /**
     * Return the id of the status bearing the given code, or null when none exists.
     */
    public function findIdByCode(string $code): ?int
    {
        return $this->findOneByCodeCached($code)?->getId();
    }
}

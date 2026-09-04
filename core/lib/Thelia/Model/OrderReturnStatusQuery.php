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

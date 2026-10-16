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

namespace Thelia\Domain\SaleUnit\Config;

/**
 * The sale unit whose configuration the static readers answer for:
 * ConfigQuery::read() for the store settings, BaseModule::getConfigValue() for
 * the modules. They are static and called from everywhere, so the scope is static
 * too.
 *
 * SaleUnitContext keeps it in step with the sale unit of the request. Code that
 * works for another unit than the current one (an order e-mail, an invoice, a
 * payment notification, a command) runs inside runFor(), which restores the
 * previous scope on every path.
 *
 * Null is the national configuration: a single shop, the back office, a command.
 */
final class SaleUnitConfigScope
{
    private static ?int $saleUnitId = null;

    public static function current(): ?int
    {
        return self::$saleUnitId;
    }

    /**
     * @internal the current sale unit is installed by SaleUnitContext; anything else uses runFor()
     */
    public static function set(?int $saleUnitId): void
    {
        self::$saleUnitId = $saleUnitId;
    }

    /**
     * Runs the callback with the configuration of the given sale unit, null being the
     * national configuration, and gives the result back.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public static function runFor(?int $saleUnitId, callable $callback): mixed
    {
        $previous = self::$saleUnitId;
        self::$saleUnitId = $saleUnitId;

        try {
            return $callback();
        } finally {
            self::$saleUnitId = $previous;
        }
    }

    /**
     * @internal
     */
    public static function reset(): void
    {
        self::$saleUnitId = null;
    }
}

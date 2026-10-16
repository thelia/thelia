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

use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\SaleUnitConfig;
use Thelia\Model\SaleUnitConfigQuery;

/**
 * The configuration of a sale unit: the store settings it overrides and the values
 * it gives the modules, each falling back on the national value it does not set.
 *
 * Reading for the current sale unit needs none of this: ConfigQuery::read() and
 * BaseModule::getConfigValue() already answer for it. This service is for the code
 * that works for a given unit, whatever the request resolved or without a request
 * at all: an order e-mail, an invoice, a payment notification, a command.
 */
readonly class SaleUnitConfigService
{
    public function __construct(
        private ?SaleUnitActivityChecker $activityChecker = null,
    ) {
    }

    /**
     * Whether any sale unit is active, so that a configuration of its own may apply.
     * A single shop answers from the shared cache, without a query.
     */
    public function isActive(): bool
    {
        return true === $this->activityChecker?->isMultiSaleUnitMode();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runFor(?int $saleUnitId, callable $callback): mixed
    {
        return SaleUnitConfigScope::runFor($saleUnitId, $callback);
    }

    /**
     * Runs the callback with the configuration of the sale unit the order was placed
     * on, the national one for a national order.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runForOrder(Order $order, callable $callback): mixed
    {
        return SaleUnitConfigScope::runFor($order->getSaleUnitId(), $callback);
    }

    /**
     * The same, from the id of the order. A shop that sells from no sale unit runs the
     * callback as it is, without reading the order.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runForOrderId(?int $orderId, callable $callback): mixed
    {
        if (null === $orderId || !$this->isActive()) {
            return $callback();
        }

        $order = OrderQuery::create()->findPk($orderId);

        if (null === $order) {
            return $callback();
        }

        return $this->runForOrder($order, $callback);
    }

    /**
     * A store setting as the given sale unit sees it: its own value, or the national
     * one. A null sale unit reads the national value.
     */
    public function getStoreValue(?int $saleUnitId, string $name, mixed $default = null): mixed
    {
        return SaleUnitConfigScope::runFor($saleUnitId, static fn (): mixed => ConfigQuery::read($name, $default));
    }

    /**
     * @throws \InvalidArgumentException when the setting cannot be set per sale unit
     */
    public function setStoreValue(int $saleUnitId, string $name, string $value): void
    {
        if (!\in_array($name, ConfigQuery::SALE_UNIT_SCOPED_NAMES, true)) {
            throw new \InvalidArgumentException(\sprintf('The store setting "%s" cannot be set per sale unit.', $name));
        }

        $config = SaleUnitConfigQuery::create()
            ->filterBySaleUnitId($saleUnitId)
            ->filterByName($name)
            ->findOne() ?? (new SaleUnitConfig())->setSaleUnitId($saleUnitId)->setName($name);

        $config->setValue($value)->save();
    }

    public function removeStoreValue(int $saleUnitId, string $name): void
    {
        SaleUnitConfigQuery::create()
            ->filterBySaleUnitId($saleUnitId)
            ->filterByName($name)
            ->findOne()
            ?->delete();
    }

    /**
     * A module setting as the given sale unit sees it: its own value, or the national
     * one. A null sale unit reads the national value.
     */
    public function getModuleValue(?int $saleUnitId, int $moduleId, string $name, mixed $default = null, ?string $locale = null): ?string
    {
        return ModuleConfigQuery::create()->getConfigValueForSaleUnit($saleUnitId, $moduleId, $name, $default, $locale);
    }

    /**
     * A secret value (a key, a password) is never read back by the admin API. Null
     * keeps the flag a stored value already has, and leaves a new one readable.
     */
    public function setModuleValue(int $saleUnitId, int $moduleId, string $name, ?string $value, ?bool $secret = null): void
    {
        ModuleConfigQuery::create()->setConfigValueForSaleUnit($saleUnitId, $moduleId, $name, $value, $secret);
    }

    public function removeModuleValue(int $saleUnitId, int $moduleId, string $name): void
    {
        ModuleConfigQuery::create()->deleteConfigValueForSaleUnit($saleUnitId, $moduleId, $name);
    }
}

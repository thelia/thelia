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

namespace Thelia\Tests\Support\Order;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Order\Service\StockDecrementer;

/**
 * The stock decrementer of the core, which also records the sale elements it is asked to
 * decrement or to increment, in order, and can fail on a given decrement the way the database would.
 */
final readonly class RecordingStockDecrementer extends StockDecrementer
{
    /** @var \ArrayObject<int, int> */
    private \ArrayObject $calls;

    /** @var \ArrayObject<int, int> */
    private \ArrayObject $incrementCalls;

    /**
     * @param int $failOnCall the call (from 1) that raises the failure instead of decrementing, 0 for none
     */
    public function __construct(
        \Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver $saleUnitStockResolver,
        private int $failOnCall = 0,
        private ?\Throwable $failure = null,
    ) {
        parent::__construct($saleUnitStockResolver);
        $this->calls = new \ArrayObject();
        $this->incrementCalls = new \ArrayObject();
    }

    public function decrement(
        int $productSaleElementsId,
        float $quantity,
        bool $guardAvailability,
        bool $allowNegativeStock,
        ConnectionInterface $connection,
        ?int $saleUnitId = null,
    ): void {
        $this->calls->append($productSaleElementsId);

        if (null !== $this->failure && \count($this->calls) === $this->failOnCall) {
            throw $this->failure;
        }

        parent::decrement($productSaleElementsId, $quantity, $guardAvailability, $allowNegativeStock, $connection, $saleUnitId);
    }

    public function increment(int $productSaleElementsId, float $quantity, ConnectionInterface $connection, ?int $saleUnitId = null): void
    {
        $this->incrementCalls->append($productSaleElementsId);

        parent::increment($productSaleElementsId, $quantity, $connection, $saleUnitId);
    }

    /**
     * @return list<int>
     */
    public function incrementedIds(): array
    {
        return array_values($this->incrementCalls->getArrayCopy());
    }

    /**
     * @return list<int>
     */
    public function decrementedIds(): array
    {
        return array_values($this->calls->getArrayCopy());
    }
}

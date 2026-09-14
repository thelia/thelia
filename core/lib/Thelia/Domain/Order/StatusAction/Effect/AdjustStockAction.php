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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Propel\Runtime\Propel;
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;
use Thelia\Domain\Order\StatusAction\OrderStatusActionPayloadField;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Puts the ordered quantities back in stock, or takes them out, on a transition
 * the merchant chose. The core keeps handling the stock of the standard
 * paid/unpaid changes by itself; this action covers the custom flows.
 */
final readonly class AdjustStockAction implements OrderStatusActionInterface
{
    public const FIELD_OPERATION = 'operation';
    public const OPERATION_INCREASE = 'increase';
    public const OPERATION_DECREASE = 'decrease';

    /** Undoes the movements of ONE run of this action inside a transaction the caller owns. */
    private const SAVEPOINT = 'thelia_adjust_stock';

    public function __construct(
        private StockDecrementer $stockDecrementer,
    ) {
    }

    public static function getType(): string
    {
        return 'adjust_stock';
    }

    public function describePayload(): array
    {
        return [
            OrderStatusActionPayloadField::choice(self::FIELD_OPERATION, 'Stock operation', [
                self::OPERATION_INCREASE => 'Put the ordered quantities back in stock',
                self::OPERATION_DECREASE => 'Take the ordered quantities out of stock',
            ]),
        ];
    }

    public function normalizePayload(array $payload): array
    {
        if ([] !== array_diff(array_keys($payload), [self::FIELD_OPERATION])) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(self::getType(), $payload, [self::FIELD_OPERATION]);
        }

        $operation = $payload[self::FIELD_OPERATION] ?? null;

        if (!\in_array($operation, [self::OPERATION_INCREASE, self::OPERATION_DECREASE], true)) {
            throw InvalidOrderStatusActionPayloadException::invalidValue(self::getType(), self::FIELD_OPERATION, 'must be "increase" or "decrease"');
        }

        return [self::FIELD_OPERATION => $operation];
    }

    public function execute(OrderStatusActionContext $context): void
    {
        $increase = self::OPERATION_INCREASE === $context->payload[self::FIELD_OPERATION];
        $checkAvailableStock = ConfigQuery::checkAvailableStock();

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        // The order is adjusted as a whole or not at all, WHATEVER the surrounding transaction.
        // The status change itself is dispatched inside one (the back office as well as the admin
        // API), so this action rarely owns it; a nested rollBack would poison the caller's commit,
        // and doing nothing left the products adjusted before the failing one decremented for good.
        // A savepoint undoes exactly what this action wrote, and nothing of what the caller wrote.
        $ownTransaction = !$connection->inTransaction();

        if ($ownTransaction) {
            $connection->beginTransaction();
        } else {
            $connection->exec('SAVEPOINT '.self::SAVEPOINT);
        }

        try {
            foreach ($context->order->getOrderProducts() as $orderProduct) {
                $productSaleElementsId = $orderProduct->getProductSaleElementsId();
                $quantity = (float) $orderProduct->getQuantity();

                if (null === $productSaleElementsId || $quantity <= 0) {
                    continue;
                }

                if ($increase) {
                    $this->stockDecrementer->increment($productSaleElementsId, $quantity, $connection);

                    continue;
                }

                $this->stockDecrementer->decrement(
                    $productSaleElementsId,
                    $quantity,
                    guardAvailability: $checkAvailableStock,
                    allowNegativeStock: !$checkAvailableStock,
                    connection: $connection,
                );
            }

            if ($ownTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT '.self::SAVEPOINT);
            }
        } catch (\Throwable $throwable) {
            if ($ownTransaction) {
                $connection->rollBack();
            } else {
                $connection->exec('ROLLBACK TO SAVEPOINT '.self::SAVEPOINT);
            }

            throw $throwable;
        }
    }
}

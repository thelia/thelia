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
        $connection->beginTransaction();

        try {
            foreach ($context->order->getOrderProducts() as $orderProduct) {
                $productSaleElementsId = $orderProduct->getProductSaleElementsId();
                $quantity = (float) $orderProduct->getQuantity();

                if (null === $productSaleElementsId || $quantity <= 0) {
                    continue;
                }

                if ($increase) {
                    $statement = $connection->prepare('UPDATE `product_sale_elements` SET `quantity` = `quantity` + :quantity WHERE `id` = :id');
                    $statement->bindValue(':quantity', $quantity);
                    $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);
                    $statement->execute();

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

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }
}

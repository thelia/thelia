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
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderProduct;
use Thelia\Module\BaseModule;

/**
 * The stock movement of the status changes the merchant chose: put the ordered quantities
 * back in stock, take them out, or leave the stock alone, optionally for some payment or
 * delivery modules only.
 *
 * On a status change, the action is not run by OrderStatusActionRunner: StockOperationActionResolver
 * reads it when the core asks which stock operation to apply, so the movement happens in the
 * transaction of the status change, and replaces the fixed rule of the core instead of adding
 * to it. execute() remains for a caller that moves the stock of an order by itself.
 */
final readonly class AdjustStockAction implements OrderStatusActionInterface
{
    public const FIELD_OPERATION = 'operation';
    public const FIELD_PAYMENT_MODULES = 'payment_modules';
    public const FIELD_DELIVERY_MODULES = 'delivery_modules';
    public const OPERATION_INCREASE = 'increase';
    public const OPERATION_DECREASE = 'decrease';
    public const OPERATION_NONE = 'none';

    private const OPERATIONS = [self::OPERATION_INCREASE, self::OPERATION_DECREASE, self::OPERATION_NONE];

    /** Prefix of the savepoint that undoes the movements of ONE run inside a transaction the caller owns. */
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
                self::OPERATION_NONE => 'Leave the stock as it is',
            ]),
            OrderStatusActionPayloadField::multipleChoice(
                self::FIELD_PAYMENT_MODULES,
                'Only for the orders paid with',
                $this->moduleChoices(BaseModule::PAYMENT_MODULE_TYPE),
            ),
            OrderStatusActionPayloadField::multipleChoice(
                self::FIELD_DELIVERY_MODULES,
                'Only for the orders delivered by',
                $this->moduleChoices(BaseModule::DELIVERY_MODULE_TYPE),
            ),
        ];
    }

    public function normalizePayload(array $payload): array
    {
        $allowedFields = [self::FIELD_OPERATION, self::FIELD_PAYMENT_MODULES, self::FIELD_DELIVERY_MODULES];

        if ([] !== array_diff(array_keys($payload), $allowedFields)) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(self::getType(), $payload, $allowedFields);
        }

        $operation = $payload[self::FIELD_OPERATION] ?? null;

        if (!\in_array($operation, self::OPERATIONS, true)) {
            throw InvalidOrderStatusActionPayloadException::invalidValue(self::getType(), self::FIELD_OPERATION, 'must be "increase", "decrease" or "none"');
        }

        $normalized = [self::FIELD_OPERATION => $operation];

        // A module listed here may be gone since: the payload stays valid, the resolver ignores it.
        foreach ([self::FIELD_PAYMENT_MODULES, self::FIELD_DELIVERY_MODULES] as $field) {
            $codes = $this->moduleCodes($payload[$field] ?? [], $field);

            if ([] !== $codes) {
                $normalized[$field] = $codes;
            }
        }

        return $normalized;
    }

    public function execute(OrderStatusActionContext $context): void
    {
        if (self::OPERATION_NONE === $context->payload[self::FIELD_OPERATION]) {
            return;
        }

        $increase = self::OPERATION_INCREASE === $context->payload[self::FIELD_OPERATION];
        $checkAvailableStock = ConfigQuery::checkAvailableStock();

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        // The order is adjusted as a whole or not at all, WHATEVER the surrounding transaction.
        // A caller often runs this inside a transaction of its own (a status change, the admin
        // API); a nested rollBack would poison the caller's commit, and doing nothing left the
        // products adjusted before the failing one decremented for good. A savepoint undoes
        // exactly what this action wrote, and nothing of what the caller wrote.
        $ownTransaction = !$connection->inTransaction();
        // One name per run: a run nested in another (from a listener, a decorated decrementer)
        // would otherwise replace the savepoint of the outer run, whose release would then fail.
        $savepoint = self::nextSavepoint();

        if ($ownTransaction) {
            $connection->beginTransaction();
        } else {
            $connection->exec('SAVEPOINT '.$savepoint);
        }

        // The stock of the sale unit the order was placed on, when the unit keeps a row
        // for the sale element: the same rule the checkout decremented by.
        $saleUnitId = $context->order->getSaleUnitId();

        try {
            // In the ascending id of the sale element, the order the placement locked the stock rows in.
            $orderProducts = $context->order->getOrderProducts()->getData();
            usort($orderProducts, static fn (OrderProduct $first, OrderProduct $second): int => (int) $first->getProductSaleElementsId() <=> (int) $second->getProductSaleElementsId());

            foreach ($orderProducts as $orderProduct) {
                $productSaleElementsId = $orderProduct->getProductSaleElementsId();
                $quantity = (float) $orderProduct->getQuantity();

                // The sale element of a pack holds no stock: its component lines carry the movement.
                if (null === $productSaleElementsId || $quantity <= 0 || !$orderProduct->movesStockOfItsOwn()) {
                    continue;
                }

                if ($increase) {
                    $this->stockDecrementer->increment($productSaleElementsId, $quantity, $connection, $saleUnitId);

                    continue;
                }

                $this->stockDecrementer->decrement(
                    $productSaleElementsId,
                    $quantity,
                    guardAvailability: $checkAvailableStock,
                    allowNegativeStock: !$checkAvailableStock,
                    connection: $connection,
                    saleUnitId: $saleUnitId,
                );
            }

            if ($ownTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT '.$savepoint);
            }
        } catch (\Throwable $throwable) {
            try {
                // A deadlock (1213) or a lost connection already rolled back the whole transaction
                // on the server: there is no savepoint left to go back to (1305), and the error
                // that says so would hide the one the caller has to see.
                if ($ownTransaction) {
                    $connection->rollBack();
                } elseif ($connection->inTransaction()) {
                    $connection->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                }
            } catch (\Throwable $rollbackFailure) {
                Tlog::getInstance()->addError('The stock adjustment could not be undone: '.$rollbackFailure->getMessage());
            }

            throw $throwable;
        } finally {
            // The rows were written in SQL, behind the back of the instance pool, and maybe
            // undone since: a findPk() later in the request would read a quantity they no longer have.
            ProductSaleElementsTableMap::clearInstancePool();
            SaleUnitProductSaleElementsTableMap::clearInstancePool();
        }
    }

    private static function nextSavepoint(): string
    {
        static $sequence = 0;

        return self::SAVEPOINT.'_'.++$sequence;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidOrderStatusActionPayloadException
     */
    private function moduleCodes(mixed $codes, string $field): array
    {
        if (!\is_array($codes) || !array_is_list($codes)) {
            throw InvalidOrderStatusActionPayloadException::invalidValue(self::getType(), $field, 'must be a list of module codes');
        }

        foreach ($codes as $code) {
            if (!\is_string($code) || '' === trim($code)) {
                throw InvalidOrderStatusActionPayloadException::invalidValue(self::getType(), $field, 'must be a list of module codes');
            }
        }

        return array_values(array_unique(array_map(trim(...), $codes)));
    }

    /**
     * @return array<string, string> module code => module title
     */
    private function moduleChoices(int $moduleType): array
    {
        $choices = [];

        foreach (ModuleQuery::create()->filterByType($moduleType)->orderByCode()->find() as $module) {
            $code = (string) $module->getCode();
            $choices[$code] = (string) ($module->getTitle() ?: $code);
        }

        return $choices;
    }
}

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

namespace Thelia\Domain\Catalog\Stock;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationConflictException;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;

/**
 * Holds the stock of the lines of a cart for a while, so that no other cart sells it meanwhile.
 *
 * A line is held by its `reserved_until`: until then, what it asks is taken from what every
 * other cart may buy (DatabaseReservedQuantity, through SellableStock). Holding again pushes
 * the moment back by the duration of the shop. The lines of a virtual product and the lines of
 * a pack are never held: the first take no stock, the second take the stock of their components.
 *
 * Every hold runs in a transaction of its own that locks the stock rows first, in ascending id,
 * and only then reads the stock and what the other carts hold: two carts holding the last unit
 * at the same moment follow one another, and the second is judged on what the first left.
 * Never inside the transaction of a caller: its view may predate the reservation of another
 * cart, and the row a write of the cart line already shares (its foreign key) would turn two
 * buyers into a deadlock. A caller that writes the cart in a transaction holds after its commit.
 *
 * A shop that does not reserve, or does not check its stock, holds nothing and reads nothing.
 */
final readonly class StockReservationService
{
    public function __construct(
        private StockSettings $stockSettings,
        private DatabaseReservedQuantity $reservedQuantity,
        private StockRowLocker $stockRowLocker,
        private CallerTransactionInterface $callerTransaction,
    ) {
    }

    /** Whether the stock of the shop is reserved at all. */
    public function isActive(): bool
    {
        return $this->stockSettings->reservationEnabled() && ConfigQuery::checkAvailableStock();
    }

    /**
     * At a step of the checkout: every line is held, or none is and the step is refused.
     *
     * @throws StockReservationRefusedException  a line asks more than what the other carts leave
     * @throws StockReservationConflictException the database broke a tie with another cart
     * @throws \LogicException                   called inside a transaction of the caller
     */
    public function holdForCheckout(Cart $cart): void
    {
        if (!$this->isActive() || (int) $cart->getId() <= 0) {
            return;
        }

        $this->hold($cart, strict: true);
    }

    /**
     * Once the cart changed (a line added, a quantity changed, another sale unit): held again
     * when the shop reserves from the cart on, or when the cart already holds a line (a buyer
     * back from the checkout). Nothing is refused here: the cart keeps its silent cap, and a
     * line the stock no longer covers is simply not held, the checkout refusing it later.
     */
    public function holdAfterCartChange(Cart $cart): void
    {
        if (!$this->isActive() || (int) $cart->getId() <= 0) {
            return;
        }

        try {
            $this->hold($cart, strict: false);
        } catch (StockReservationConflictException $conflict) {
            // The line is in the cart already: a tie broken by the database leaves it unheld,
            // which the next change or the checkout settles, rather than failing the change.
            Tlog::getInstance()->warning('The stock of cart '.$cart->getId().' was not held: '.$conflict->getMessage());
        }
    }

    /**
     * Locks the stock rows of the sale elements in the transaction of the caller, when the shop
     * reserves: an order or a payment that takes the stock reads the reservations after it.
     *
     * @param list<int> $productSaleElementsIds
     */
    public function lockStockRows(array $productSaleElementsIds, ConnectionInterface $connection): void
    {
        if (!$this->isActive()) {
            return;
        }

        $this->stockRowLocker->lock($productSaleElementsIds, $connection);
    }

    /**
     * What the carts other than the one asking hold of each sale element, read once for all of
     * them on the connection of the transaction that locked their stock rows. Nothing is read
     * when the shop does not reserve.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> by sale element id; a sale element nothing holds is absent
     */
    public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId, ConnectionInterface $connection): array
    {
        if (!$this->isActive() || [] === $productSaleElementsIds) {
            return [];
        }

        return $this->reservedQuantity->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $excludedCartId, $connection);
    }

    /**
     * Nothing of the cart is held any more: the cart became an order whose stock is now sold, or
     * the order whose stock was left for its payment is paid or cancelled. By primary key, in the
     * transaction of the caller.
     */
    public function releaseCart(int $cartId, ConnectionInterface $connection): void
    {
        if (!$this->isActive() || $cartId <= 0) {
            return;
        }

        $statement = $connection->prepare('SELECT `id` FROM `cart_item` WHERE `cart_id` = :cartId AND `reserved_until` IS NOT NULL');
        $statement->bindValue(':cartId', $cartId, \PDO::PARAM_INT);
        $statement->execute();

        $this->writeReservedUntil(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN)), null, $connection);
    }

    /**
     * @throws StockReservationRefusedException
     * @throws StockReservationConflictException
     */
    private function hold(Cart $cart, bool $strict): void
    {
        $cartId = (int) $cart->getId();
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);

        if ($this->callerTransaction->isOpen($connection)) {
            // A step of the checkout would go on with nothing held: a defect of the caller.
            if ($strict) {
                throw new \LogicException('The stock of a cart cannot be held for the checkout inside a transaction of the caller: hold it once the caller has committed.');
            }

            // A change of the cart inside a transaction (the cart API, the ordering by reference)
            // holds after its commit; any other caller leaves the cart unheld until the checkout.
            Tlog::getInstance()->warning('The stock of cart '.$cartId.' was not held: the cart changed inside a transaction of the caller.');

            return;
        }

        // Read before the transaction, with no lock: what to lock follows from it, and a plain
        // read inside the transaction before the locks would freeze its view too early.
        $lines = $this->linesOf($cartId, $connection);

        if (!$strict && StockReservationScope::Checkout === $this->stockSettings->reservationScope() && !\in_array(true, array_column($lines, 'held'), true)) {
            return;
        }

        $lines = array_values(array_filter($lines, static fn (array $line): bool => !$line['virtual'] && !$line['bundle'] && $line['quantity'] > 0));

        if ([] === $lines) {
            return;
        }

        $productSaleElementsIds = array_values(array_unique(array_column($lines, 'productSaleElementsId')));
        $saleUnitId = null === $cart->getSaleUnitId() ? null : (int) $cart->getSaleUnitId();

        $connection->beginTransaction();

        try {
            $this->stockRowLocker->lock($productSaleElementsIds, $connection);

            $stock = $this->stockOf($productSaleElementsIds, $saleUnitId, $connection);
            $reserved = $this->reservedQuantity->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $cartId, $connection);

            /** @var array<int, float> $asked */
            $asked = [];

            foreach ($lines as $line) {
                $asked[$line['productSaleElementsId']] = ($asked[$line['productSaleElementsId']] ?? 0.0) + $line['quantity'];
            }

            $held = [];
            $released = [];

            foreach ($lines as $line) {
                $productSaleElementsId = $line['productSaleElementsId'];
                $ceiling = isset($stock[$productSaleElementsId])
                    ? SellableStock::ceilingOf(...$stock[$productSaleElementsId])
                    : 0.0;
                $covered = null === $ceiling
                    || round($asked[$productSaleElementsId], 6) <= round($ceiling - ($reserved[$productSaleElementsId] ?? 0.0), 6);

                if ($covered) {
                    $held[] = $line['id'];

                    continue;
                }

                if ($strict) {
                    throw new StockReservationRefusedException($line['reference']);
                }

                $released[] = $line['id'];
            }

            $this->writeReservedUntil($held, $this->stockSettings->reservationDurationMinutes(), $connection);
            $this->writeReservedUntil($released, null, $connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            if (LockConflictDetector::isLockConflict($throwable)) {
                throw new StockReservationConflictException($throwable);
            }

            throw $throwable;
        }
    }

    /**
     * The lines of the cart, as stored.
     *
     * @return list<array{id: int, productSaleElementsId: int, quantity: float, bundle: bool, virtual: bool, reference: string, held: bool}>
     */
    private function linesOf(int $cartId, ConnectionInterface $connection): array
    {
        $statement = $connection->prepare(
            'SELECT `cart_item`.`id`, `cart_item`.`product_sale_elements_id`, `cart_item`.`quantity`, `cart_item`.`bundle_composition` IS NOT NULL, '
            .'`product`.`virtual`, `product`.`ref`, COALESCE(`cart_item`.`reserved_until` > NOW(), 0) '
            .'FROM `cart_item` INNER JOIN `product` ON `product`.`id` = `cart_item`.`product_id` '
            .'WHERE `cart_item`.`cart_id` = :cartId ORDER BY `cart_item`.`id`',
        );
        $statement->bindValue(':cartId', $cartId, \PDO::PARAM_INT);
        $statement->execute();

        $lines = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $productSaleElementsId, $quantity, $bundle, $virtual, $reference, $held]) {
            $lines[] = [
                'id' => (int) $id,
                'productSaleElementsId' => (int) $productSaleElementsId,
                'quantity' => round((float) $quantity, 6),
                'bundle' => 1 === (int) $bundle,
                'virtual' => 1 === (int) $virtual,
                'reference' => (string) $reference,
                'held' => 1 === (int) $held,
            ];
        }

        return $lines;
    }

    /**
     * The stock the cart sells from (the own row of its sale unit when the unit keeps one, the
     * national counter otherwise) and the sale mode, read after the locks.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, array{0: float, 1: OutOfStockPolicy, 2: ?float}> the arguments of SellableStock::ceilingOf(), by sale element id
     */
    private function stockOf(array $productSaleElementsIds, ?int $saleUnitId, ConnectionInterface $connection): array
    {
        $statement = $connection->prepare(
            'SELECT `pse`.`id`, `pse`.`quantity`, `pse`.`out_of_stock_policy`, `pse`.`preorder_limit`, `own`.`quantity` '
            .'FROM `product_sale_elements` `pse` '
            .'LEFT JOIN `sale_unit_product_sale_elements` `own` ON `own`.`product_sale_elements_id` = `pse`.`id` AND `own`.`sale_unit_id` = :saleUnitId '
            .'WHERE `pse`.`id` IN ('.implode(',', array_map('intval', $productSaleElementsIds)).')',
        );
        $statement->bindValue(':saleUnitId', $saleUnitId ?? 0, \PDO::PARAM_INT);
        $statement->execute();

        $stock = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $quantity, $policy, $limit, $ownQuantity]) {
            $stock[(int) $id] = [
                round((float) ($ownQuantity ?? $quantity), 6),
                OutOfStockPolicy::fromStored(null === $policy ? null : (int) $policy),
                null === $limit ? null : (float) $limit,
            ];
        }

        return $stock;
    }

    /**
     * @param list<int> $cartItemIds
     * @param int|null  $minutes     from now on, or null to hold nothing any more
     */
    private function writeReservedUntil(array $cartItemIds, ?int $minutes, ConnectionInterface $connection): void
    {
        if ([] === $cartItemIds) {
            return;
        }

        $ids = implode(',', array_map('intval', $cartItemIds));

        if (null === $minutes) {
            $connection->exec("UPDATE `cart_item` SET `reserved_until` = NULL WHERE `id` IN ($ids)");

            return;
        }

        $statement = $connection->prepare("UPDATE `cart_item` SET `reserved_until` = NOW() + INTERVAL :minutes MINUTE WHERE `id` IN ($ids)");
        $statement->bindValue(':minutes', $minutes, \PDO::PARAM_INT);
        $statement->execute();
    }
}

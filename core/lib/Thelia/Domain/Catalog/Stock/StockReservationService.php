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
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationConflictException;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRateLimitedException;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;

/**
 * Holds the stock of the lines of a cart for a while, so that no other cart sells it meanwhile.
 *
 * A line is held by its `reserved_until`: until then, what it asks is taken from what every
 * other cart may buy (DatabaseReservedQuantity, through SellableStock). Holding again pushes
 * the moment back by the duration of the shop, never past the longest hold counted from the first
 * hold of the line (`reserved_since`, StockSettings::longestHoldMinutes()): a cart that comes back
 * to the checkout again and again cannot keep the stock off sale for ever. Past that bound the line
 * is still told as held until the purge, a removal or an order releases it; only then may a new
 * hold, which spends the budget, start it again. A line whose date ran out keeps its first hold
 * when it is held again, and a hold of a line past the bound dates it now, so that the stock stays
 * on sale: holding again at every expiry never starts the bound over. Only a line left untouched
 * for longer (StockSettings::lapsedHoldMinutes(), a window in which other carts could buy the unit)
 * is held as a new one: its first hold is now, it spends the budget, and its old hold is told as
 * released before the new one is told as reserved. The lines of a virtual product and the lines of
 * a pack are never held: the first take no stock, the second take the stock of their components.
 *
 * Every hold runs in a transaction of its own that locks the stock rows first, in ascending id,
 * and only then reads the stock and what the other carts hold: two carts holding the last unit
 * at the same moment follow one another, and the second is judged on what the first left.
 * Never inside the transaction of a caller: its view may predate the reservation of another
 * cart, and the row a write of the cart line already shares (its foreign key) would turn two
 * buyers into a deadlock. A caller that writes the cart in a transaction holds after its commit.
 *
 * A shop that does not reserve, or does not check its stock, holds nothing and reads nothing. The
 * lines it held before the reservation, or the check of the stock, was turned off are still read
 * when they go (a removal, an order, a purge), so that each of them is told as released.
 *
 * A hold that takes a line not told as held yet spends one of the budgets of the cart and of the
 * address it comes from (StockReservationLimiter), before it writes anything; a hold that only
 * pushes back the date of lines already told as held, or changes their quantity, spends nothing.
 * Over the budgets, the lines already held still go on with their date and their quantity, the new
 * ones are left out: the checkout is then refused with the delay to wait, and a change of the cart
 * leaves its new line unheld, in silence, as a line the stock does not cover.
 *
 * What a hold or a release changes is told to the listeners once committed
 * (StockReservationAnnouncer): the lines that were not held and are (reserved), the lines that
 * were held and are not (released), a line whose quantity changed while held (released with the
 * quantity it was told with, then reserved with the new one). A line is told as held from its
 * first hold until it is released, whether or not its date ran out meanwhile: the date only says
 * when the stock goes back on sale, and the purge, a removal or an order is what releases the
 * line. A hold that takes back a line whose date ran out only pushes the date, unless the line
 * lapsed (released, then reserved, as a new hold). The quantity a
 * line was told with is kept on the line (`reserved_quantity`, written with `reserved_until`,
 * emptied with it): whoever changed the quantity of the line since, with or without a hold, the
 * release tells the quantity of the reservation.
 */
final readonly class StockReservationService
{
    public function __construct(
        private StockSettings $stockSettings,
        private DatabaseReservedQuantity $reservedQuantity,
        private StockRowLocker $stockRowLocker,
        private CallerTransactionInterface $callerTransaction,
        private StockReservationLimiter $limiter,
        private StockReservationAnnouncer $announcer,
        private HeldCartLineReader $heldLines,
    ) {
    }

    /** Whether the stock of the shop is reserved at all. */
    public function isActive(): bool
    {
        return $this->stockSettings->reservationEnabled() && ConfigQuery::checkAvailableStock();
    }

    /**
     * Whether a line may be told as held: the shop reserves, or it held a line before the
     * reservation was turned off. No query: a shop that never reserved reads nothing.
     */
    public function mayHaveHeldLines(): bool
    {
        return $this->isActive() || $this->stockSettings->linesMayBeHeld();
    }

    /**
     * At a step of the checkout: every line is held, or none is and the step is refused.
     *
     * @throws StockReservationRefusedException     a line asks more than what the other carts leave
     * @throws StockReservationConflictException    the database broke a tie with another cart
     * @throws StockReservationRateLimitedException the cart, or its address, holds more often than a buyer does
     * @throws \LogicException                      called inside a transaction of the caller
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
        if ($cartId <= 0 || !$this->mayHaveHeldLines()) {
            return;
        }

        // Locked by primary key, after the stock rows of the caller, and read under the lock: a
        // purge that cleared a line since the view of the caller was frozen has told it already.
        $held = $this->heldLines->lockedOfCart($cartId, $connection);

        $this->writeReleased(array_map(static fn (StockReservationLine $line): int => $line->cartItemId, $held), $connection);

        // Told once the transaction of the caller is over, if the lines read back unheld then.
        $this->announcer->released($cartId, $held);
    }

    /**
     * The lines of a cart that hold stock now, read before a write that removes them (a line
     * deleted, a cart emptied or purged), to be told with announceReleased() once it is written.
     * Nothing when the shop never reserved.
     *
     * @return list<StockReservationLine>
     */
    public function heldLinesOf(int $cartId, ?int $cartItemId = null): array
    {
        if ($cartId <= 0 || !$this->mayHaveHeldLines()) {
            return [];
        }

        return $this->heldLines->ofCart($cartId, $cartItemId);
    }

    /**
     * @param list<int> $cartIds
     *
     * @return array<int, list<StockReservationLine>> by cart id; a cart that holds nothing is absent
     */
    public function heldLinesOfCarts(array $cartIds): array
    {
        if ([] === $cartIds || !$this->mayHaveHeldLines()) {
            return [];
        }

        return $this->heldLines->ofCarts($cartIds);
    }

    /**
     * Lines read by heldLinesOf() before they were removed: told as released, or once the
     * transaction the removal is part of is over, when they read back unheld.
     *
     * @param list<StockReservationLine> $lines
     */
    public function announceReleased(int $cartId, array $lines): void
    {
        $this->announcer->released($cartId, $lines);
    }

    /**
     * The lines a write is about to delete with something else (a product, a sale element, a
     * customer, a product becoming a pack), read now and told as released once the transaction of
     * the write is over, those that are gone. To be called inside that transaction, before the
     * delete: the lines removed by a foreign key leave no trace to read afterwards.
     */
    public function announceTheReleaseOfLinesAmong(CartItemQuery $lines): void
    {
        if (!$this->mayHaveHeldLines()) {
            return;
        }

        foreach ($this->heldLines->among($lines) as $cartId => $held) {
            $this->announcer->released($cartId, $held);
        }
    }

    /**
     * @throws StockReservationRefusedException
     * @throws StockReservationConflictException
     * @throws StockReservationRateLimitedException
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
        // read inside the transaction before the locks would freeze its view too early. It only
        // chooses what to lock and whether to spend a token; the transaction decides on the lines
        // it has locked (their quantity, whether they are told as held).
        $lines = $this->linesOf($cartId, $connection);

        if (!$strict && StockReservationScope::Checkout === $this->stockSettings->reservationScope() && !\in_array(true, array_column($lines, 'held'), true)) {
            return;
        }

        $lines = array_values(array_filter($lines, static fn (array $line): bool => !$line['virtual'] && !$line['bundle'] && $line['quantity'] > 0));

        if ([] === $lines) {
            return;
        }

        // Only a line not told as held yet costs a hold: pushing back the date of the others, or
        // changing their quantity, is free. The budget is spent here, before anything is written,
        // not once the hold is over: requests in parallel would all find it unspent.
        $secondsToWait = null;

        if (\in_array(false, array_column($lines, 'told'), true)) {
            if ($strict) {
                // A line that cannot be held costs no token: the checkout refuses it, by name,
                // whatever the budget says (the transaction below judges it again, under the locks).
                $this->refuseTheFirstUncoveredLine($cart, $lines, $connection);
            }

            $secondsToWait = $this->limiter->spend($cartId);

            if (null !== $secondsToWait) {
                // The lines already held go on; the new ones stay in the cart, unheld.
                $lines = array_values(array_filter($lines, static fn (array $line): bool => $line['told']));
            }
        }

        // A line a purge cleared between the read above and the locks is told as new by the
        // transaction without a token (a window of a few milliseconds, accepted); over the budget,
        // it is left out instead.
        if ([] !== $lines) {
            // Before the first date is written: the lines read later when they go, whatever the
            // settings are then (StockSettings::linesMayBeHeld()).
            $this->stockSettings->rememberThatLinesAreHeld();
            $this->holdInTransaction($cart, $lines, $strict, null === $secondsToWait, $connection);
        }

        if (null === $secondsToWait) {
            return;
        }

        if ($strict) {
            throw new StockReservationRateLimitedException($secondsToWait);
        }

        // The line stays in the cart, unheld: the checkout holds it, or refuses it, later.
        Tlog::getInstance()->warning('The stock of cart '.$cartId.' was not held: the cart, or its address, held too often.');
    }

    /**
     * Judges every line against the stock, with no lock: the same coverage as the transaction (a
     * strict hold refuses any line the stock does not cover, a line told as held whose date ran out
     * included), read once before the budget is spent.
     *
     * @param list<array{id: int, productSaleElementsId: int, quantity: float, bundle: bool, virtual: bool, reference: string, held: bool, told: bool}> $lines
     *
     * @throws StockReservationRefusedException
     */
    private function refuseTheFirstUncoveredLine(Cart $cart, array $lines, ConnectionInterface $connection): void
    {
        $cartId = (int) $cart->getId();
        $productSaleElementsIds = array_values(array_unique(array_column($lines, 'productSaleElementsId')));
        $saleUnitId = null === $cart->getSaleUnitId() ? null : (int) $cart->getSaleUnitId();
        $stock = $this->stockOf($productSaleElementsIds, $saleUnitId, $connection);
        $reserved = $this->reservedQuantity->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $cartId, $connection);
        $asked = $this->askedBySaleElement($lines);

        foreach ($lines as $line) {
            if (!self::isCovered($line['productSaleElementsId'], $asked, $stock, $reserved)) {
                throw new StockReservationRefusedException($line['reference']);
            }
        }
    }

    /**
     * @param list<array{id: int, productSaleElementsId: int, quantity: float, bundle: bool, virtual: bool, reference: string, held: bool, told: bool}> $lines
     *
     * @return array<int, float> the quantity asked by all the lines, by sale element id
     */
    private function askedBySaleElement(array $lines): array
    {
        $asked = [];

        foreach ($lines as $line) {
            $asked[$line['productSaleElementsId']] = ($asked[$line['productSaleElementsId']] ?? 0.0) + $line['quantity'];
        }

        return $asked;
    }

    /**
     * @param array<int, float>                                           $asked
     * @param array<int, array{0: float, 1: OutOfStockPolicy, 2: ?float}> $stock
     * @param array<int, float>                                           $reserved
     */
    private static function isCovered(int $productSaleElementsId, array $asked, array $stock, array $reserved): bool
    {
        $ceiling = isset($stock[$productSaleElementsId])
            ? SellableStock::ceilingOf(...$stock[$productSaleElementsId])
            : 0.0;

        return null === $ceiling
            || round($asked[$productSaleElementsId], 6) <= round($ceiling - ($reserved[$productSaleElementsId] ?? 0.0), 6);
    }

    /**
     * @param list<array{id: int, productSaleElementsId: int, quantity: float, bundle: bool, virtual: bool, reference: string, held: bool, told: bool}> $lines
     * @param bool                                                                                                                                      $mayStartLines false when the budget is spent: a line found not told as held under the locks is left out
     *
     * @throws StockReservationRefusedException
     * @throws StockReservationConflictException
     */
    private function holdInTransaction(Cart $cart, array $lines, bool $strict, bool $mayStartLines, ConnectionInterface $connection): void
    {
        $cartId = (int) $cart->getId();
        $productSaleElementsIds = array_values(array_unique(array_column($lines, 'productSaleElementsId')));
        $saleUnitId = null === $cart->getSaleUnitId() ? null : (int) $cart->getSaleUnitId();

        $connection->beginTransaction();

        try {
            $this->stockRowLocker->lock($productSaleElementsIds, $connection);

            // Every lock first (the stock rows, then all the lines by ascending id), then the reads:
            // two holds of the same cart follow one another, and the second sees what the first
            // wrote; a purge of the same lines waits for this commit or has finished before it.
            // The quantity and what each line was told with are those of the locked rows.
            $locked = $this->heldLines->lockedLines(array_column($lines, 'id'), $connection);
            $lapsedAfterSeconds = 60 * $this->stockSettings->lapsedHoldMinutes();
            $announcedBefore = [];
            $lapsed = [];
            $current = [];

            foreach ($lines as $line) {
                $row = $locked[$line['id']] ?? null;
                // Told as held, but untouched long enough for another cart to have bought the unit.
                $isLapsed = null !== $row && null !== $row['announced'] && $row['expiredForSeconds'] >= $lapsedAfterSeconds;

                // Deleted since the read, emptied, or a new hold with the budget spent: not held now.
                if (null === $row || $row['quantity'] <= 0 || (!$mayStartLines && (null === $row['announced'] || $isLapsed))) {
                    continue;
                }

                $announcedBefore[$line['id']] = $row['announced'];

                if ($isLapsed) {
                    $lapsed[$line['id']] = true;
                }

                $current[] = [...$line, 'quantity' => $row['quantity']];
            }

            $lines = $current;
            $stock = $this->stockOf($productSaleElementsIds, $saleUnitId, $connection);
            $reserved = $this->reservedQuantity->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $cartId, $connection);

            $asked = $this->askedBySaleElement($lines);

            /** @var array<int, float> $held the quantity of each line the hold covers, by line id */
            $held = [];
            $released = [];
            $started = [];
            $stopped = [];

            foreach ($lines as $line) {
                $productSaleElementsId = $line['productSaleElementsId'];
                $covered = self::isCovered($productSaleElementsId, $asked, $stock, $reserved);
                $toldQuantity = $announcedBefore[$line['id']];

                if ($covered) {
                    $held[$line['id']] = $line['quantity'];

                    if (null === $toldQuantity) {
                        $started[] = new StockReservationLine($line['id'], $productSaleElementsId, $line['quantity']);
                    } elseif (isset($lapsed[$line['id']])) {
                        // A new hold of a line whose old one was never released: both are told.
                        $stopped[] = new StockReservationLine($line['id'], $productSaleElementsId, $toldQuantity);
                        $started[] = new StockReservationLine($line['id'], $productSaleElementsId, $line['quantity']);
                    } elseif ($toldQuantity !== $line['quantity']) {
                        // Held already, with another quantity than the one it was told with.
                        $stopped[] = new StockReservationLine($line['id'], $productSaleElementsId, $toldQuantity);
                        $started[] = new StockReservationLine($line['id'], $productSaleElementsId, $line['quantity']);
                    }

                    continue;
                }

                if ($strict) {
                    throw new StockReservationRefusedException($line['reference']);
                }

                $released[] = $line['id'];

                if (null !== $toldQuantity) {
                    $stopped[] = new StockReservationLine($line['id'], $productSaleElementsId, $toldQuantity);
                }
            }

            $this->writeReservation($held, array_keys($lapsed), $this->stockSettings->reservationDurationMinutes(), $this->stockSettings->longestHoldMinutes(), $connection);
            $this->writeReleased($released, $connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            if (LockConflictDetector::isLockConflict($throwable)) {
                throw new StockReservationConflictException($throwable);
            }

            throw $throwable;
        }

        // After the commit, outside the try: a listener cannot turn a hold into a conflict.
        $this->announcer->releasedNow($cartId, $stopped);
        $this->announcer->reserved($cartId, $started);
    }

    /**
     * The lines of the cart, as stored: `held` when their date is still ahead (they hold stock now),
     * `told` when they have a date (they were told as held and not released since) that did not
     * run out long ago: a line untouched since longer than StockSettings::lapsedHoldMinutes() is
     * held again as a new one, which spends the budget.
     *
     * @return list<array{id: int, productSaleElementsId: int, quantity: float, bundle: bool, virtual: bool, reference: string, held: bool, told: bool}>
     */
    private function linesOf(int $cartId, ConnectionInterface $connection): array
    {
        $statement = $connection->prepare(
            'SELECT `cart_item`.`id`, `cart_item`.`product_sale_elements_id`, `cart_item`.`quantity`, `cart_item`.`bundle_composition` IS NOT NULL, '
            .'`product`.`virtual`, `product`.`ref`, COALESCE(`cart_item`.`reserved_until` > NOW(), 0), '
            .'COALESCE(`cart_item`.`reserved_until` > NOW() - INTERVAL :lapsedMinutes MINUTE, 0) '
            .'FROM `cart_item` INNER JOIN `product` ON `product`.`id` = `cart_item`.`product_id` '
            .'WHERE `cart_item`.`cart_id` = :cartId ORDER BY `cart_item`.`id`',
        );
        $statement->bindValue(':cartId', $cartId, \PDO::PARAM_INT);
        $statement->bindValue(':lapsedMinutes', $this->stockSettings->lapsedHoldMinutes(), \PDO::PARAM_INT);
        $statement->execute();

        $lines = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $productSaleElementsId, $quantity, $bundle, $virtual, $reference, $held, $told]) {
            $lines[] = [
                'id' => (int) $id,
                'productSaleElementsId' => (int) $productSaleElementsId,
                'quantity' => round((float) $quantity, 6),
                'bundle' => 1 === (int) $bundle,
                'virtual' => 1 === (int) $virtual,
                'reference' => (string) $reference,
                'held' => 1 === (int) $held,
                'told' => 1 === (int) $told,
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
     * The lines the hold covers: their date pushed back and the quantity they are told with. The
     * date is pushed back by the duration, but never past the longest hold counted from the first
     * hold of the line, which a line held for the first time (no date, or no start) takes now, and
     * so does a lapsed line (held again as a new one). Any other line keeps its start, its date run
     * out or not, until it is released. A line past the bound is dated now, never in the past: the
     * window it must stay untouched before a new hold starts again at each attempt.
     *
     * The start is written first and read again by the date: the same expression gives the same
     * moment whether the database assigns the columns one after the other or all at once.
     *
     * @param array<int, float> $quantityByCartItemId
     * @param list<int>         $lapsedCartItemIds    held again as new ones: their start is now
     * @param int               $minutes              from now on
     * @param int               $longestMinutes       from the first hold of the line
     */
    private function writeReservation(array $quantityByCartItemId, array $lapsedCartItemIds, int $minutes, int $longestMinutes, ConnectionInterface $connection): void
    {
        $since = 'IF(`reserved_until` IS NULL OR `reserved_since` IS NULL'
            .([] === $lapsedCartItemIds ? '' : ' OR `id` IN ('.implode(',', array_map('intval', $lapsedCartItemIds)).')')
            .', NOW(), `reserved_since`)';

        $idsByQuantity = [];

        foreach ($quantityByCartItemId as $cartItemId => $quantity) {
            $idsByQuantity[(string) $quantity][] = $cartItemId;
        }

        foreach ($idsByQuantity as $quantity => $cartItemIds) {
            $statement = $connection->prepare(
                'UPDATE `cart_item` SET `reserved_since` = '.$since.', '
                .'`reserved_until` = GREATEST(NOW(), LEAST(NOW() + INTERVAL :minutes MINUTE, '.$since.' + INTERVAL :longestMinutes MINUTE)), '
                .'`reserved_quantity` = :quantity WHERE `id` IN ('.implode(',', array_map('intval', $cartItemIds)).')',
            );
            $statement->bindValue(':minutes', $minutes, \PDO::PARAM_INT);
            $statement->bindValue(':longestMinutes', $longestMinutes, \PDO::PARAM_INT);
            $statement->bindValue(':quantity', (string) $quantity);
            $statement->execute();
        }
    }

    /**
     * @param list<int> $cartItemIds
     */
    private function writeReleased(array $cartItemIds, ConnectionInterface $connection): void
    {
        if ([] === $cartItemIds) {
            return;
        }

        $connection->exec('UPDATE `cart_item` SET `reserved_until` = NULL, `reserved_quantity` = NULL, `reserved_since` = NULL WHERE `id` IN ('.implode(',', array_map('intval', $cartItemIds)).')');
    }
}

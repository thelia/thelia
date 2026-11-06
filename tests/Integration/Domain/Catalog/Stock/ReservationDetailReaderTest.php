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

namespace Thelia\Tests\Integration\Domain\Catalog\Stock;

use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Stock\ReservationDetail;
use Thelia\Domain\Catalog\Stock\ReservationDetailReader;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The detail of the active reservations by sale element: the quantity held, the number of
 * distinct carts and the nearest expiry, read in one query. A sale element nothing holds is
 * absent; an expired reservation is never counted.
 */
final class ReservationDetailReaderTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reserve(true);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheDetailIsGroupedBySaleElementWithCartsAndNearestExpiry(): void
    {
        $first = $this->product();
        $second = $this->product();
        $free = $this->product();

        [$cartA, $lineA] = $this->cartWith($first, 2);
        [, $lineB] = $this->cartWith($first, 3);
        $lineA2 = $this->factory->cartItem($cartA, $first, null, ['quantity' => 1.0]);
        [, $lineC] = $this->cartWith($second, 4);

        $this->holdUntil($lineA, '+ INTERVAL 10 MINUTE');
        $this->holdUntil($lineA2, '+ INTERVAL 20 MINUTE');
        $this->holdUntil($lineB, '+ INTERVAL 5 MINUTE');
        $this->holdUntil($lineC, '+ INTERVAL 30 MINUTE');

        $detail = $this->reader()->detailOf([$this->pseId($first), $this->pseId($second), $this->pseId($free)]);

        self::assertSame([$this->pseId($first), $this->pseId($second)], array_keys($detail), 'A sale element nothing holds is absent.');
        self::assertInstanceOf(ReservationDetail::class, $detail[$this->pseId($first)]);
        self::assertSame(6.0, $detail[$this->pseId($first)]->quantity);
        self::assertSame(2, $detail[$this->pseId($first)]->cartCount, 'Two lines of one cart count as one cart.');
        self::assertSame($this->untilOf($lineB), $detail[$this->pseId($first)]->nextExpiry->format('Y-m-d H:i:s'));
        self::assertSame(4.0, $detail[$this->pseId($second)]->quantity);
        self::assertSame(1, $detail[$this->pseId($second)]->cartCount);
        self::assertSame($this->untilOf($lineC), $detail[$this->pseId($second)]->nextExpiry->format('Y-m-d H:i:s'));
    }

    public function testAnExpiredReservationIsNeitherCountedNorGivesTheNearestExpiry(): void
    {
        $product = $this->product();
        [, $live] = $this->cartWith($product, 2);
        [, $expired] = $this->cartWith($product, 5);
        $this->holdUntil($live, '+ INTERVAL 10 MINUTE');
        $this->holdUntil($expired, '- INTERVAL 1 MINUTE');

        $detail = $this->reader()->detailOf([$this->pseId($product)]);

        self::assertSame(2.0, $detail[$this->pseId($product)]->quantity);
        self::assertSame(1, $detail[$this->pseId($product)]->cartCount);
        self::assertSame($this->untilOf($live), $detail[$this->pseId($product)]->nextExpiry->format('Y-m-d H:i:s'));
    }

    public function testASaleElementWhoseOnlyReservationExpiredIsAbsent(): void
    {
        $product = $this->product();
        [, $expired] = $this->cartWith($product, 5);
        $this->holdUntil($expired, '- INTERVAL 1 MINUTE');

        self::assertSame([], $this->reader()->detailOf([$this->pseId($product)]));
    }

    public function testAnEmptyListReadsNothingAndAsksNoQuery(): void
    {
        self::assertSame([], $this->reader()->detailOf([]));
    }

    public function testAShopThatDoesNotReserveReadsNothing(): void
    {
        $product = $this->product();
        [, $line] = $this->cartWith($product, 2);
        $this->holdUntil($line, '+ INTERVAL 10 MINUTE');
        $this->reserve(false);

        self::assertSame([], $this->reader()->detailOf([$this->pseId($product)]));
    }

    public function testTheOwnStockOfASaleUnitIsNotTheNationalCounter(): void
    {
        $product = $this->product();
        $unit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($unit, $product->getDefaultSaleElements(), 1.0);
        [$unitCart, $unitLine] = $this->cartWith($product, 3);
        $unitCart->setSaleUnitId($unit->getId())->save($this->connection());
        [, $nationalLine] = $this->cartWith($product, 2);
        $this->holdUntil($unitLine, '+ INTERVAL 10 MINUTE');
        $this->holdUntil($nationalLine, '+ INTERVAL 10 MINUTE');

        $detail = $this->reader()->detailOf([$this->pseId($product)]);

        self::assertSame(2.0, $detail[$this->pseId($product)]->quantity);
        self::assertSame(1, $detail[$this->pseId($product)]->cartCount);
    }

    public function testTheQueryRunsInOneStatementAndUsesIndexes(): void
    {
        $products = [$this->product(), $this->product(), $this->product()];
        $ids = [];

        foreach ($products as $product) {
            [, $line] = $this->cartWith($product, 1);
            $this->holdUntil($line, '+ INTERVAL 10 MINUTE');
            $ids[] = $this->pseId($product);
        }

        // A table of a few rows is read whole by the optimizer: fill it with lines of another sale element.
        $filler = $this->product();
        [$fillerCart] = $this->cartWith($filler, 1);
        $this->connection()->exec(
            'INSERT INTO cart_item (cart_id, product_id, quantity, product_sale_elements_id, reserved_until) '
            .'WITH RECURSIVE seq (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM seq WHERE n < 900) '
            .'SELECT '.(int) $fillerCart->getId().', '.(int) $filler->getId().', 1, '.$this->pseId($filler).', NOW() - INTERVAL 1 HOUR FROM seq',
        );

        $reader = $this->reader();
        $sql = $reader->query(\count($ids));
        self::assertSame(\count($ids), substr_count($sql, '?'), 'One placeholder by id, nothing concatenated.');
        self::assertCount(3, $reader->detailOf($ids));

        $plan = $this->connection()->prepare('EXPLAIN '.$sql);
        $plan->execute($ids);
        $rows = $plan->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            self::assertNotSame('ALL', $row['type'], 'No table is read whole: '.json_encode($row));
            self::assertNotNull($row['key'], 'Every table is read through an index: '.json_encode($row));
        }
    }

    private function reader(): ReservationDetailReader
    {
        return $this->getService(ReservationDetailReader::class);
    }

    private function reserve(bool $enabled): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, $enabled ? '1' : '0');
        ConfigQuery::resetCache();
    }

    private function product(): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity(50)->save($this->connection());

        return $product;
    }

    private function pseId(Product $product): int
    {
        return (int) $product->getDefaultSaleElements()->getId();
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function cartWith(Product $product, int $quantity): array
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        return [$cart, $this->factory->cartItem($cart, $product, null, ['quantity' => (float) $quantity])];
    }

    private function holdUntil(CartItem $line, string $interval): void
    {
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() '.$interval.' WHERE id = '.(int) $line->getId());
    }

    private function untilOf(CartItem $line): string
    {
        return (string) $this->connection()->query('SELECT reserved_until FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function connection(): \Propel\Runtime\Connection\ConnectionWrapper
    {
        return Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
    }
}

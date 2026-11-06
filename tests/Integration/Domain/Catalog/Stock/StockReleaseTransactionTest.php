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

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Core\Event\ProductSaleElement\StockReservationEvent;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockReservationAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A release written inside the transaction of a caller (an order consuming its cart, a write of
 * the cart API) waits for the end of that transaction, and is never told when it is rolled back:
 * the line is still held. Committed fixtures, deleted by hand: a rollback needs a real transaction.
 */
final class StockReleaseTransactionTest extends ActionIntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var list<array{0: string, 1: int, 2: list<StockReservationLine>}> */
    private array $told = [];

    private ?\Closure $listener = null;

    private int $productId = 0;

    private int $cartId = 0;

    private int $lineId = 0;

    private int $saleElementsId = 0;

    /** @var array<string, string|null> */
    private array $settingsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED, StockSettings::RESERVATION_SCOPE, StockSettings::RESERVATION_DURATION] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();

        $this->listener = function (StockReservationEvent $event): void {
            $this->told[] = [$event->getCartId(), $event->getLines()];
        };
        $this->dispatcher->addListener(TheliaEvents::STOCK_RELEASED, $this->listener);

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity(5)->save();
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 2.0]);
        $this->productId = (int) $product->getId();
        $this->cartId = (int) $cart->getId();
        $this->lineId = (int) $line->getId();
        $this->saleElementsId = (int) $line->getProductSaleElementsId();

        $this->getService(CheckoutFacade::class)->holdStock($cart);
        self::assertTrue($this->isHeld());
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->dispatcher->removeListener(TheliaEvents::STOCK_RELEASED, $this->listener);
        }

        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);

        while ($connection->inTransaction()) {
            $connection->rollBack();
        }

        try {
            if ($this->cartId > 0) {
                $connection->exec('DELETE FROM cart WHERE id = '.$this->cartId);
            }

            if ($this->productId > 0) {
                $categoryIds = $connection->query('SELECT category_id FROM product_category WHERE product_id = '.$this->productId)->fetchAll(\PDO::FETCH_COLUMN);
                $connection->exec('DELETE FROM product WHERE id = '.$this->productId);

                if ([] !== $categoryIds) {
                    $connection->exec('DELETE FROM category WHERE id IN ('.implode(',', array_map('intval', $categoryIds)).')');
                }
            }
        } finally {
            foreach ($this->settingsBefore as $name => $value) {
                ConfigQuery::write($name, $value ?? '');
            }

            ConfigQuery::resetCache();
        }

        parent::tearDown();
    }

    public function testAReleaseInsideATransactionIsToldOnceItIsCommittedAndTheTransactionIsOver(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $announcer = $this->getService(StockReservationAnnouncer::class);
        $connection->beginTransaction();

        try {
            $this->getService(StockReservationService::class)->releaseCart($this->cartId, $connection);
            $announcer->flush();

            self::assertSame([], $this->told, 'Nothing is told while the transaction of the caller is open.');
        } finally {
            $connection->commit();
        }

        self::assertSame([], $this->told, 'The commit tells nothing by itself.');

        $announcer->flush();

        self::assertEquals([[$this->cartId, [new StockReservationLine($this->lineId, $this->saleElementsId, 2.0)]]], $this->told);

        $announcer->flush();

        self::assertCount(1, $this->told, 'Told once.');
    }

    public function testAReleaseOfATransactionThatWasRolledBackIsNeverTold(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->getService(StockReservationService::class)->releaseCart($this->cartId, $connection);
        } finally {
            $connection->rollBack();
        }

        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertSame([], $this->told);
        self::assertTrue($this->isHeld(), 'The line is still held.');
    }

    public function testAReleaseFollowedByAHoldOfTheSameLineIsNotTold(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->getService(StockReservationService::class)->releaseCart($this->cartId, $connection);
        } finally {
            $connection->commit();
        }

        $connection->exec('UPDATE cart_item SET reserved_until = NOW() + INTERVAL 5 MINUTE WHERE id = '.$this->lineId);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertSame([], $this->told, 'The line reads held when the transaction is over: it was not released for good.');
    }

    /**
     * The release rolled back leaves the line told as held; its date runs out before the flush: the
     * flush tells nothing (the line was not released), the purge tells it, once.
     */
    public function testAReleaseRolledBackWhoseDateRanOutBeforeTheFlushIsToldOnceByThePurge(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->getService(StockReservationService::class)->releaseCart($this->cartId, $connection);
        } finally {
            $connection->rollBack();
        }

        $connection->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id = '.$this->lineId);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertSame([], $this->told, 'The line was never released: the flush has nothing to tell.');

        $this->getService(StockReservationPurger::class)->purgeExpired();
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([[$this->cartId, [new StockReservationLine($this->lineId, $this->saleElementsId, 2.0)]]], array_values(array_filter($this->told, fn (array $told): bool => $this->cartId === $told[0])));
    }

    /**
     * The transaction of the caller froze its view while the line was held; the purge cleared the
     * line and told it since. The release the caller writes reads the line under its lock: it is
     * not held any more, and the caller tells nothing (the purge told it already).
     */
    public function testAReleaseOfALineThePurgeClearedSinceTheViewOfTheCallerWasFrozenTellsNothing(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $serviceContainer = Propel::getServiceContainer();
        $manager = $serviceContainer->getConnectionManager(CartItemTableMap::DATABASE_NAME);

        if (!$manager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        $purge = ConnectionFactory::create($manager->getConfiguration(), $serviceContainer->getAdapter(CartItemTableMap::DATABASE_NAME));
        $connection->beginTransaction();

        try {
            // The first read of the transaction: the line is held in its view from now on.
            self::assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM cart_item WHERE id = '.$this->lineId.' AND reserved_until IS NOT NULL')->fetchColumn());

            $purge->exec('UPDATE cart_item SET reserved_until = NULL, reserved_quantity = NULL WHERE id = '.$this->lineId);

            $this->getService(StockReservationService::class)->releaseCart($this->cartId, $connection);
        } finally {
            $connection->commit();
        }

        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertSame([], $this->told, 'The line was released by the purge: a second release would double it.');
    }

    private function isHeld(): bool
    {
        return 1 === (int) Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME)->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.$this->lineId)->fetchColumn();
    }
}

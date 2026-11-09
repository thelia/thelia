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

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A cart that comes back to the checkout pushes its hold back for free, but never past the longest
 * hold counted from its first hold: an anonymous cart that refreshes its hold every fourteen minutes
 * (a script) cannot keep the last unit off sale for ever. Within the bound a refresh keeps the unit,
 * and a release starts the count again.
 */
final class StockReservationLongestHoldTest extends ActionIntegrationTestCase
{
    private const STEP_MINUTES = 14;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->connection());
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAScriptThatRefreshesItsHoldLetsTheLastUnitGoOnceTheLongestHoldIsOver(): void
    {
        $service = $this->getService(StockReservationService::class);
        $product = $this->product(1);

        [$script] = $this->cartWith($product);
        $service->holdForCheckout($script);

        for ($refresh = 1; $refresh <= 12; ++$refresh) {
            $this->age($script, self::STEP_MINUTES);
            $service->holdForCheckout($script);
        }

        [$buyer, $buyerLine] = $this->cartWith($product);

        try {
            $service->holdForCheckout($buyer);
        } catch (StockReservationRefusedException $refusal) {
            self::fail(\sprintf(
                'After %d minutes of free refreshes by one anonymous cart, a real buyer is still refused the last unit (%s): the hold never ends.',
                self::STEP_MINUTES * 12,
                $refusal->getMessage(),
            ));
        }

        self::assertTrue($this->isHeld($buyerLine), 'Once the refreshed hold has outlived the longest hold, the buyer holds the unit.');
    }

    public function testWithinTheLongestHoldARefreshKeepsTheUnit(): void
    {
        $service = $this->getService(StockReservationService::class);
        $product = $this->product(1);

        [$first, $firstLine] = $this->cartWith($product);
        $service->holdForCheckout($first);

        for ($refresh = 1; $refresh <= 3; ++$refresh) {
            $this->age($first, self::STEP_MINUTES);
            $service->holdForCheckout($first);
        }

        self::assertTrue($this->isHeld($firstLine), 'Forty-two minutes after its first hold, a buyer going back and forth still holds the unit.');

        [$other] = $this->cartWith($product);

        $this->expectException(StockReservationRefusedException::class);
        $service->holdForCheckout($other);
    }

    public function testAReleaseStartsTheLongestHoldAgain(): void
    {
        $service = $this->getService(StockReservationService::class);
        $product = $this->product(1);

        [$cart, $line] = $this->cartWith($product);
        $service->holdForCheckout($cart);

        for ($refresh = 1; $refresh <= 5; ++$refresh) {
            $this->age($cart, self::STEP_MINUTES);
            $service->holdForCheckout($cart);
        }

        self::assertFalse($this->isHeld($line), 'Seventy minutes after its first hold, the line is no longer held.');

        $this->getService(StockReservationPurger::class)->purgeExpired();
        $service->holdForCheckout($cart);

        self::assertTrue($this->isHeld($line), 'Released by the purge, the line is held again by a new hold, for a new longest hold.');
    }

    /**
     * Moves every date stored for the cart and its lines back in time, as if the minutes had gone by.
     */
    private function age(Cart $cart, int $minutes): void
    {
        $connection = $this->connection();
        $columns = $connection->query(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('cart', 'cart_item') AND DATA_TYPE IN ('datetime', 'timestamp')",
        )->fetchAll(\PDO::FETCH_NUM);

        foreach ($columns as [$table, $column]) {
            $where = 'cart' === $table ? '`id` = '.(int) $cart->getId() : '`cart_id` = '.(int) $cart->getId();
            $connection->exec(\sprintf('UPDATE `%1$s` SET `%2$s` = `%2$s` - INTERVAL %3$d MINUTE WHERE %4$s AND `%2$s` IS NOT NULL', $table, $column, $minutes, $where));
        }
    }

    private function product(int $quantity): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity($quantity)->save($this->connection());

        return $product;
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function cartWith(Product $product): array
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        return [$cart, $this->factory->cartItem($cart, $product, null, ['quantity' => 1.0])];
    }

    private function isHeld(CartItem $line): bool
    {
        return 1 === (int) $this->connection()->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function connection(): ConnectionWrapper
    {
        return Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
    }
}

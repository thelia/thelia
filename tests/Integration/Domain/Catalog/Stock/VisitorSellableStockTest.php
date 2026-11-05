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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorCart;
use Thelia\Domain\Catalog\Stock\VisitorSellableStock;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\RecordsStatements;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * What the visitor served may still buy, read for a page or an API answer: the cart of the
 * visitor is found without opening a session, once per request, and the sale elements of a page
 * are answered together (one read of the reservations whatever their number).
 */
final class VisitorSellableStockTest extends ActionIntegrationTestCase
{
    use RecordsStatements;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();

        // The transaction this test wraps itself in is not one of a caller: a hold runs in it.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->connection());
        $this->getService(VisitorCart::class)->reset();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();
        $this->getService(VisitorCart::class)->reset();

        parent::tearDown();
    }

    public function testAReadOfTheCatalogueNeverOpensASessionNorRestoresACart(): void
    {
        $product = $this->product(1);
        $this->held($product, 1);
        $restored = 0;
        $listener = static function () use (&$restored): void {
            ++$restored;
        };
        $this->dispatcher->addListener(TheliaEvents::CART_RESTORE_CURRENT, $listener);

        try {
            $answer = $this->getService(VisitorSellableStock::class)->forPse($this->saleElementsOf($product));
            $many = $this->getService(VisitorSellableStock::class)->forMany([$this->saleElementsOf($product)]);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::CART_RESTORE_CURRENT, $listener);
        }

        self::assertSame(0.0, $answer, 'Another cart holds the only unit.');
        self::assertSame(0.0, $many[(int) $this->saleElementsOf($product)->getId()]['available']);
        self::assertFalse($this->session()->isStarted(), 'A read of the catalogue opens no session.');
        self::assertSame(0, $restored, 'It restores no cart either.');
    }

    public function testTheCartOfAStartedSessionIsNotHeldAgainstItsVisitor(): void
    {
        $product = $this->product(1);
        $cart = $this->held($product, 1);
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
        $visitorSellableStock = $this->getService(VisitorSellableStock::class);

        self::assertSame(1.0, $visitorSellableStock->forPse($this->saleElementsOf($product)), 'What the visitor holds is still theirs.');
        self::assertSame(1.0, $visitorSellableStock->forMany([$this->saleElementsOf($product)])[(int) $this->saleElementsOf($product)->getId()]['available']);
    }

    public function testTheCartIsLookedUpOncePerRequest(): void
    {
        $product = $this->product(1);
        $cart = $this->held($product, 1);
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
        $visitorSellableStock = $this->getService(VisitorSellableStock::class);
        $saleElements = $this->saleElementsOf($product);

        $statements = $this->statementsDuring(static function () use ($visitorSellableStock, $saleElements): void {
            for ($read = 0; $read < 5; ++$read) {
                $visitorSellableStock->forPse($saleElements);
            }
        });

        self::assertCount(1, array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `cart` ')), 'The cart is read once, not once a read.');
    }

    public function testAnotherRequestFindsItsOwnCart(): void
    {
        $product = $this->product(1);
        $cartOfTheFirst = $this->held($product, 1);
        $visitorCart = $this->getService(VisitorCart::class);
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cartOfTheFirst->getId());

        self::assertSame($cartOfTheFirst->getId(), $visitorCart->currentId());

        $stack = $this->getService(RequestStack::class);
        $other = clone $stack->getCurrentRequest();
        $other->setSession(new Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
        $stack->push($other);

        try {
            self::assertNull($visitorCart->currentId(), 'The session of the other request holds no cart.');
        } finally {
            $stack->pop();
        }
    }

    public function testAShopThatDoesNotReserveLooksNoCartUp(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        ConfigQuery::resetCache();
        $product = $this->product(2);
        $this->session()->set(Session::SESSION_CART_ID_NAME, $this->factory->cart(null, ['currency' => $this->factory->currency()])->getId());
        $visitorSellableStock = $this->getService(VisitorSellableStock::class);
        $saleElements = $this->saleElementsOf($product);

        $statements = $this->statementsDuring(static function () use ($visitorSellableStock, $saleElements): void {
            $visitorSellableStock->forPse($saleElements);
        });

        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `cart`'))));
    }

    public function testAPageIsAnsweredWithOneReadOfTheReservationsWhateverItsSize(): void
    {
        $products = [$this->product(5), $this->product(5), $this->product(5)];
        $this->held($products[0], 2);
        $visitorSellableStock = $this->getService(VisitorSellableStock::class);
        $few = $this->saleElementsOfAll([$products[0]]);
        $many = $this->saleElementsOfAll($products);

        $reads = static fn (array $statements): int => \count(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'SUM(`cart_item`.`quantity`)')));
        $small = $reads($this->statementsDuring(static function () use ($visitorSellableStock, $few): void {
            $visitorSellableStock->forMany($few);
        }));
        $answers = [];
        $large = $reads($this->statementsDuring(static function () use ($visitorSellableStock, $many, &$answers): void {
            $answers = $visitorSellableStock->forMany($many);
        }));

        self::assertSame(1, $small);
        self::assertSame(1, $large, 'Three sale elements, as many reads as one.');
        self::assertSame(3.0, $answers[(int) $products[0]->getDefaultSaleElements()->getId()]['available'], '5 in stock, 2 held by another cart.');
        self::assertSame(5.0, $answers[(int) $products[1]->getDefaultSaleElements()->getId()]['available']);
    }

    public function testThePreorderOfAPageFollowsTheStockTheOtherCartsLeave(): void
    {
        $unlimited = $this->product(3, OutOfStockPolicy::Unlimited);
        $this->held($unlimited, 3);
        $refusing = $this->product(3);
        $this->held($refusing, 3);
        $plain = $this->product(3, OutOfStockPolicy::Unlimited);

        $answers = $this->getService(VisitorSellableStock::class)->forMany($this->saleElementsOfAll([$unlimited, $refusing, $plain]));

        self::assertTrue($answers[(int) $unlimited->getDefaultSaleElements()->getId()]['preorder'], 'Stock 3, all held: the next unit is a preorder.');
        self::assertFalse($answers[(int) $refusing->getDefaultSaleElements()->getId()]['preorder']);
        self::assertFalse($answers[(int) $plain->getDefaultSaleElements()->getId()]['preorder'], 'Stock is there for the next unit.');
    }

    public function testACartIsAnsweredLineByLineWithOneReadOfTheReservationsAndOneOfTheSaleElements(): void
    {
        $taken = $this->product(2);
        $this->held($taken, 2);
        $preorder = $this->product(1, OutOfStockPolicy::Unlimited);
        $plain = $this->product(10);
        $virtual = $this->product(0);
        $virtual->setVirtual(1)->save($this->connection());
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $lines = [
            'taken' => $this->factory->cartItem($cart, $taken, null, ['quantity' => 1.0]),
            'preorder' => $this->factory->cartItem($cart, $preorder, null, ['quantity' => 3.0]),
            'plain' => $this->factory->cartItem($cart, $plain, null, ['quantity' => 4.0]),
            'virtual' => $this->factory->cartItem($cart, $virtual, null, ['quantity' => 1.0]),
        ];
        $cart->reload();
        $cart->getCartItems();
        $sellableStock = $this->getService(SellableStock::class);
        $answer = [];

        $statements = $this->statementsDuring(static function () use ($sellableStock, $cart, &$answer): void {
            $answer = $sellableStock->forCart($cart);
        });

        self::assertSame(['available' => 0.0, 'preorder' => false], $answer[(int) $lines['taken']->getId()], 'Physical 2, held 2 by another cart, a refusing mode.');
        self::assertSame(['available' => null, 'preorder' => true], $answer[(int) $lines['preorder']->getId()], 'Sells without limit, 3 asked of 1.');
        self::assertSame(['available' => 10.0, 'preorder' => false], $answer[(int) $lines['plain']->getId()]);
        self::assertSame(['available' => null, 'preorder' => false], $answer[(int) $lines['virtual']->getId()]);
        self::assertCount(1, array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'SUM(`cart_item`.`quantity`)')), 'One read of the reservations for four lines.');
        self::assertCount(1, array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `product_sale_elements`')), 'One read of the sale elements.');
    }

    public function testACartDoesNotHoldItsOwnLinesAgainstItself(): void
    {
        $product = $this->product(1);
        $cart = $this->held($product, 1);
        $cart->reload();

        $answer = $this->getService(SellableStock::class)->forCart($cart);

        self::assertSame(1.0, $answer[(int) $cart->getCartItems()->getFirst()->getId()]['available']);
    }

    public function testThePurgeOfAShopThatDoesNotReserveRunsNoQuery(): void
    {
        $product = $this->product(1);
        $cart = $this->held($product, 1);
        $line = $cart->getCartItems()->getFirst() ?? \Thelia\Model\CartItemQuery::create()->filterByCartId($cart->getId())->findOne($this->connection());
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id = '.(int) $line->getId());
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        ConfigQuery::resetCache();
        $purger = $this->getService(StockReservationPurger::class);
        $cleared = -1;

        $statements = $this->statementsDuring(static function () use ($purger, &$cleared): void {
            $cleared = $purger->purgeExpired();
        });

        self::assertSame(0, $cleared);
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => !str_contains($statement, 'FROM `config`'))), 'Nothing to clear when nothing is reserved: no query on the carts.');
    }

    public function testThePurgeReadsTheExpiredDatesThroughTheIndexOfTheDate(): void
    {
        $indexes = $this->connection()->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart_item' AND INDEX_NAME = 'idx_cart_item_reserved_until' ORDER BY SEQ_IN_INDEX")->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(['reserved_until'], $indexes, 'The purge looks lines up by their date alone: an index starting with it.');
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    private function product(int $quantity, OutOfStockPolicy $policy = OutOfStockPolicy::Refuse): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()
            ->setQuantity($quantity)
            ->setOutOfStockPolicy($policy->value)
            ->save($this->connection());

        return $product;
    }

    /**
     * A cart, other than the visitor's, that holds that many units of the product.
     */
    private function held(Product $product, int $quantity): Cart
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $this->factory->cartItem($cart, $product, null, ['quantity' => (float) $quantity]);
        $this->getService(CheckoutFacade::class)->holdStock($cart);

        return $cart;
    }

    private function saleElementsOf(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->findPk($product->getDefaultSaleElements()->getId(), $this->connection());
    }

    /**
     * @param list<Product> $products
     *
     * @return list<ProductSaleElements>
     */
    private function saleElementsOfAll(array $products): array
    {
        return array_map(fn (Product $product): ProductSaleElements => $this->saleElementsOf($product), $products);
    }

    private function connection(): \Propel\Runtime\Connection\ConnectionWrapper
    {
        return Propel::getWriteConnection(\Thelia\Model\Map\CartItemTableMap::DATABASE_NAME);
    }
}

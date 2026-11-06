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

namespace Thelia\Tests\Integration\Core\Template;

use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The `STOCK` of a line of the cart loop is what the line may still be raised to: the number the
 * cart itself refuses above, so what the other carts hold of the stock is not offered.
 */
final class CartLoopStockTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn(Propel::getWriteConnection('TheliaMain'));
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testTheStockOfALineLeavesOutWhatTheOtherCartsHold(): void
    {
        $product = $this->product(5);
        $other = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $this->factory->cartItem($other, $product, null, ['quantity' => 2.0]);
        $this->getService(CheckoutFacade::class)->holdStock($other);
        $mine = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $line = $this->factory->cartItem($mine, $product, null, ['quantity' => 1.0]);
        $this->session()->setSessionCart($mine);

        $rows = $this->rows();

        self::assertCount(1, $rows);
        self::assertSame($line->getId(), $rows[0]['ITEM_ID']);
        self::assertSame(3.0, (float) $rows[0]['STOCK'], 'Physical 5, another cart holds 2: three may be reached.');
        self::assertSame(
            (float) $rows[0]['STOCK'],
            $this->getService(SellableStock::class)->forPse(
                ProductSaleElementsQuery::create()->findPk($product->getDefaultSaleElements()->getId()),
                null,
                null,
                (int) $mine->getId(),
            ),
            'The number the cart refuses above.',
        );
    }

    public function testWhatTheCartHoldsItselfIsStillItsOwn(): void
    {
        $product = $this->product(5);
        $mine = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $this->factory->cartItem($mine, $product, null, ['quantity' => 4.0]);
        $this->getService(CheckoutFacade::class)->holdStock($mine);
        $this->session()->setSessionCart($mine);

        self::assertSame(5.0, (float) $this->rows()[0]['STOCK']);
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    private function product(int $quantity): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity($quantity)->save();

        return $product;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $rows = [];

        foreach ($this->getService(LoopExecutor::class)->execute('cart', ['lang' => $lang->getId()]) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}

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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Form\CartAdd;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Every reader that offers a quantity to the visitor reads the stock of the sale
 * unit it buys from when the unit keeps one: the sale element loop, the cart loop,
 * the legacy add-to-cart form and the copy of a cart. With no unit resolved they
 * read the national counter, and ask nothing of the sale unit tables.
 */
final class SaleUnitStockReadersTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private Currency $currency;

    private Product $product;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');

        $this->factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 10.0],
        );
        $this->productSaleElements = $this->product->getDefaultSaleElements();
        $this->saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 4.0);
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        $this->getService(SaleUnitPricingActivityChecker::class)->reset();
        $this->getService(EffectivePriceCatalog::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheSaleElementLoopServesTheUnitStockAndPrice(): void
    {
        $this->factory->saleUnitProductPrice($this->saleUnit, $this->productSaleElements, $this->currency, 7.5);
        $this->buyFromTheUnit();

        $row = $this->saleElementRow();

        self::assertSame(4.0, (float) $row['QUANTITY']);
        self::assertSame(1, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(7.5, (float) $row['PROMO_PRICE'], 0.000001);
    }

    public function testTheSaleElementLoopServesTheNationalStockWithNoUnit(): void
    {
        self::assertSame(100.0, (float) $this->saleElementRow()['QUANTITY']);
    }

    public function testTheBackOfficeSaleElementLoopServesTheNationalStock(): void
    {
        $this->buyFromTheUnit();

        self::assertSame(100.0, (float) $this->saleElementRow(['backend_context' => 1])['QUANTITY']);
    }

    public function testTheSaleElementLoopOfASingleShopReadsNoSaleUnitTable(): void
    {
        // A single shop: no unit is active, and the shared entry says so once read.
        $this->saleUnit->setActive(false)->save($this->getPropelConnection());
        $this->getService(SaleUnitActivityChecker::class)->forgetAfterTheRequest();
        $this->saleElementRow();

        $statements = $this->recordSqlQueries(function (): void {
            $this->getService(SaleUnitActivityChecker::class)->reset();
            $this->getService(EffectivePriceCatalog::class)->reset();
            $this->saleElementRow();
        });

        self::assertNotSame([], $statements, 'The loop is recorded: it reads the sale element.');
        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/`sale_unit(_\w+)?`/', $statement),
        )));
    }

    public function testTheCartLoopServesTheUnitStock(): void
    {
        $this->buyFromTheUnit();
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $cart->setSaleUnitId($this->saleUnit->getId())->save($this->getPropelConnection());
        $this->factory->cartItem($cart, $this->product, $this->productSaleElements);
        $this->session()->setSessionCart($cart);

        $rows = iterator_to_array($this->getService(LoopExecutor::class)->execute('cart', []));

        self::assertCount(1, $rows);
        self::assertSame(4.0, (float) $rows[0]->getVarVal()['STOCK']);
    }

    public function testTheAddToCartFormRefusesAQuantityBeyondTheUnitStock(): void
    {
        $this->buyFromTheUnit();

        self::assertCount(1, $this->submitAddToCart(5)->get('quantity')->getErrors());
        self::assertCount(0, $this->submitAddToCart(4)->get('quantity')->getErrors());
    }

    public function testTheAddToCartFormAcceptsWhatTheUnitHoldsThoughTheNationalCounterIsShort(): void
    {
        $this->productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        $this->buyFromTheUnit();

        self::assertCount(0, $this->submitAddToCart(3)->get('quantity')->getErrors());
    }

    public function testTheAddToCartFormJudgesOnTheNationalCounterWithNoUnit(): void
    {
        self::assertCount(0, $this->submitAddToCart(50)->get('quantity')->getErrors());
    }

    public function testACopiedCartKeepsWhatItsUnitCanSell(): void
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $cart->setSaleUnitId($this->saleUnit->getId())->save($this->getPropelConnection());
        $this->factory->cartItem($cart, $this->product, $this->productSaleElements, ['quantity' => 5.0]);

        $copy = $this->copy($cart);

        self::assertSame($this->saleUnit->getId(), $copy->getSaleUnitId(), 'The copy stays on the unit of the cart it copies.');
        self::assertCount(0, $copy->getCartItems(), 'The unit holds 4: a line of 5 is not copied, though the national stock covers it.');
    }

    public function testACopiedCartKeepsALineItsUnitHolds(): void
    {
        $this->productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $cart->setSaleUnitId($this->saleUnit->getId())->save($this->getPropelConnection());
        $this->factory->cartItem($cart, $this->product, $this->productSaleElements, ['quantity' => 3.0]);

        self::assertCount(1, $this->copy($cart)->getCartItems(), 'The unit holds 4 though the national counter is empty.');
    }

    public function testACopiedNationalCartIsJudgedOnTheNationalCounter(): void
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $this->factory->cartItem($cart, $this->product, $this->productSaleElements, ['quantity' => 5.0]);

        $copy = $this->copy($cart);

        self::assertNull($copy->getSaleUnitId());
        self::assertCount(1, $copy->getCartItems());
    }

    private function buyFromTheUnit(): void
    {
        $this->getService(SaleUnitContext::class)->setSaleUnit($this->saleUnit);
    }

    /**
     * @return array<string, mixed>
     */
    private function saleElementRow(array $arguments = []): array
    {
        foreach ($this->getService(LoopExecutor::class)->execute(
            'product_sale_elements',
            $arguments + ['id' => $this->productSaleElements->getId(), 'currency' => $this->currency->getId()],
        ) as $row) {
            return $row->getVarVal();
        }

        self::fail('The sale element loop returned no row.');
    }

    private function submitAddToCart(int $quantity): FormInterface
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(CartAdd::class, FormType::class, [], ['csrf_protection' => false])
            ->getForm();

        $form->submit([
            'product' => (string) $this->product->getId(),
            'product_sale_elements_id' => (string) $this->productSaleElements->getId(),
            'quantity' => (string) $quantity,
            'append' => '1',
            'newness' => '0',
        ]);

        return $form;
    }

    private function copy(Cart $cart): Cart
    {
        $copy = $cart->duplicate('copy-'.uniqid(), null, $this->currency, $this->getService(EventDispatcherInterface::class));
        self::assertInstanceOf(Cart::class, $copy);

        return $copy;
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}

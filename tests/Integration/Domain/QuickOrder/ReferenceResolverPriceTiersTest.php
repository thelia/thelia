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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Domain\QuickOrder\Service\ReferenceResolver;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The quick order prices each line at the tier of the quantity typed for it, as the cart that
 * receives the line will: the table shows what the cart will charge.
 *
 * The example: the list prices a sale element 10 from one unit and 9 from fifty. Fifty typed on
 * one line, or thirty and twenty on two lines that settle on the same sale element (its own
 * reference and the one of its product, which the cart merges), are 9 each; one unit is 10.
 */
final class ReferenceResolverPriceTiersTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use PriceListFixtures;

    private FixtureFactory $factory;

    private ReferenceResolver $resolver;

    private Customer $customer;

    private Product $product;

    private ProductSaleElements $pse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->resolver = $this->getService(ReferenceResolver::class);
        $this->customer = $this->factory->customer($this->factory->customerTitle());
        $this->product = $this->pricedProduct(100.0);
        $this->pse = $this->product->getDefaultSaleElements();
        $this->pse->setRef('PSE-'.bin2hex(random_bytes(4)))->save($this->getPropelConnection());
        $this->forgetRequestMemos();
    }

    protected function tearDown(): void
    {
        $this->forgetRequestMemos();

        parent::tearDown();
    }

    public function testEachLineIsPricedAtTheTierOfItsQuantity(): void
    {
        $list = $this->priceList('TIERS');
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '10', 1);
        $this->priceListItem($list, $this->pse, '9', 50);
        $this->forgetRequestMemos();

        self::assertEqualsWithDelta(10.0, $this->unitPriceOf(1), 0.000001);
        self::assertEqualsWithDelta(10.0, $this->unitPriceOf(49), 0.000001);
        self::assertEqualsWithDelta(9.0, $this->unitPriceOf(50), 0.000001, 'the tier of the quantity typed, not the first one');
    }

    public function testTwoLinesThatSettleOnTheSameSaleElementReachTheTierTogetherAsTheCartMergesThem(): void
    {
        $list = $this->priceList('TIERS');
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '10', 1);
        $this->priceListItem($list, $this->pse, '9', 50);
        $this->forgetRequestMemos();

        $table = $this->resolver->resolve($this->customer, new ReferenceQuantityLines([
            new ReferenceQuantity((string) $this->pse->getRef(), 30),
            new ReferenceQuantity((string) $this->product->getRef(), 20),
        ]), Currency::getDefaultCurrency());

        self::assertSame((int) $this->pse->getId(), $table->lines[0]->productSaleElementsId);
        self::assertSame((int) $this->pse->getId(), $table->lines[1]->productSaleElementsId);
        self::assertEqualsWithDelta(9.0, $table->lines[0]->untaxedUnitPrice, 0.000001);
        self::assertEqualsWithDelta(9.0, $table->lines[1]->untaxedUnitPrice, 0.000001);
    }

    /**
     * The exclusive list holds the sale element from ten units up: the line is for sale at any quantity,
     * at the catalog price below the tier and at the tier from it.
     */
    public function testALineOfAnExclusiveListThatStartsAtATierIsForSaleAtEveryQuantity(): void
    {
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '9', 10);
        $this->forgetRequestMemos();

        self::assertEqualsWithDelta(100.0, $this->unitPriceOf(1), 0.000001);
        self::assertEqualsWithDelta(9.0, $this->unitPriceOf(20), 0.000001);
        self::assertSame(LineStatus::Resolved, $this->lineOf(20)->status);
    }

    /**
     * Tiers 1 -> 10 and 50 -> 9; the cart already holds 30; the buyer types 20: the cart will hold 50
     * of it, at 9, and the table says 9 (it said 10 for the 20 typed alone).
     */
    public function testTheQuantityAlreadyInTheCartCountsForTheTierOfTheLine(): void
    {
        $list = $this->priceList('TIERS');
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '10', 1);
        $this->priceListItem($list, $this->pse, '9', 50);
        $cart = $this->factory->cart($this->customer);
        $this->factory->cartItem($cart, $this->product, $this->pse, ['quantity' => 30.0]);
        $this->forgetRequestMemos();

        self::assertEqualsWithDelta(9.0, $this->unitPriceOf(20, $cart), 0.000001, '30 held + 20 typed = 50');
        self::assertEqualsWithDelta(10.0, $this->unitPriceOf(19, $cart), 0.000001, '30 held + 19 typed = 49');
        self::assertEqualsWithDelta(10.0, $this->unitPriceOf(20), 0.000001, 'with no cart given, the quantity typed alone decides');
    }

    public function testWhatTheCartHoldsOfAnotherSaleElementOrOffersDoesNotCount(): void
    {
        $list = $this->priceList('TIERS');
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '10', 1);
        $this->priceListItem($list, $this->pse, '9', 50);
        $other = $this->pricedProduct(100.0);
        $cart = $this->factory->cart($this->customer);
        $this->factory->cartItem($cart, $other, null, ['quantity' => 40.0]);
        $this->factory->cartItem($cart, $this->product, $this->pse, ['quantity' => 40.0, 'isOffered' => 1]);
        $this->forgetRequestMemos();

        self::assertEqualsWithDelta(10.0, $this->unitPriceOf(20, $cart), 0.000001);
    }

    /**
     * What the table promises is what the cart charges, the cart held beforehand included.
     */
    public function testTheTableOfAnAdditionToACartThatHoldsTheSaleElementShowsThePriceTheLineIsChargedAt(): void
    {
        $list = $this->priceList('TIERS');
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $this->pse, '10', 1);
        $this->priceListItem($list, $this->pse, '9', 50);
        $cart = $this->factory->cart($this->customer);
        $this->factory->cartItem($cart, $this->product, $this->pse, ['quantity' => 30.0]);
        $this->forgetRequestMemos();

        $table = $this->getService(QuickOrderFacade::class)->addToCart($this->customer, $cart, new ReferenceQuantityLines([new ReferenceQuantity((string) $this->pse->getRef(), 20, (int) $this->pse->getId())]));

        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne() ?? self::fail('The cart has no line.');
        self::assertEqualsWithDelta(50.0, (float) $line->getQuantity(), 0.000001);
        self::assertEqualsWithDelta((float) $line->getPrice(), (float) $table->lines[0]->untaxedUnitPrice, 0.000001);
        self::assertEqualsWithDelta(9.0, (float) $table->lines[0]->untaxedUnitPrice, 0.000001);
    }

    private function unitPriceOf(int $quantity, ?Cart $cart = null): float
    {
        return (float) $this->lineOf($quantity, $cart)->untaxedUnitPrice;
    }

    private function lineOf(int $quantity, ?Cart $cart = null): QuickOrderLine
    {
        return $this->resolver->resolve(
            $this->customer,
            new ReferenceQuantityLines([new ReferenceQuantity((string) $this->pse->getRef(), $quantity, (int) $this->pse->getId())]),
            Currency::getDefaultCurrency(),
            $cart,
        )->lines[0];
    }
}

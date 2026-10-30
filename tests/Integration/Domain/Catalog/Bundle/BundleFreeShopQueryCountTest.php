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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Cart\Bundle\CartCompositionResolver;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A shop that sells no bundle pays nothing for them on its hot paths: the loops of a catalog
 * page, the sale elements of a product page, the addition of a line to the cart. A shop that has
 * some asks once for a page, and once for a bundle posted.
 */
final class BundleFreeShopQueryCountTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use RecordsSqlQueries;

    public function testTheProductAndSaleElementLoopsAskNothingOfBundles(): void
    {
        $products = [$this->component($this->factory, '10', 3), $this->component($this->factory, '20', 3), $this->component($this->factory, '30', 3)];
        $ids = implode(',', array_map(static fn ($product): int => (int) $product->getId(), $products));

        $statements = $this->recordSqlQueries(function () use ($ids, $products): void {
            $this->rowsOf('product', ['id' => $ids]);
            $this->rowsOf('product_sale_elements', ['product' => (int) $products[0]->getId()]);
        });

        self::assertSame([], self::bundleStatements($statements));
    }

    public function testTheWeightOfACartOfLinesSoldAsTheyAreReadsNoProductInAShopThatSellsBundles(): void
    {
        $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->component($this->factory, '10', 5), null, ['quantity' => 1.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);
        $this->factory->cartItem($cart, $this->component($this->factory, '20', 5), null, ['quantity' => 2.0, 'price' => '20.000000', 'promoPrice' => '20.000000']);
        $cart = CartQuery::create()->findPk($cart->getId());

        $statements = $this->recordSqlQueries(static function () use ($cart): void {
            $cart->getWeight();
        });

        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/FROM `product`(\s|$)/', $statement),
        )), 'A line with no composition that no promotion offers is no pack: its product is not read to find out.');
    }

    public function testTheSaleElementsOfAProductPageAskNothingOfBundles(): void
    {
        $product = $this->component($this->factory, '10', 3);

        $statements = $this->recordSqlQueries(fn () => $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()));

        self::assertSame([], self::bundleStatements($statements));
    }

    public function testAddingASimpleLineToTheCartAsksNothingOfBundles(): void
    {
        $product = $this->component($this->factory, '10', 3);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);

        $statements = $this->recordSqlQueries(function () use ($cart, $product): void {
            $this->dispatch(
                (new CartEvent($cart))
                    ->setProductId((int) $product->getId())
                    ->setProductSaleElementsId((int) $this->saleElementsOf($product)->getId())
                    ->setQuantity(1)
                    ->setNewness(true)
                    ->setAppend(true),
                TheliaEvents::CART_ADDITEM,
            );
        });

        self::assertSame([], self::bundleStatements($statements));
    }

    public function testTheLoopsAskForTheBundlesOfAPageOnceWhenTheShopHasSome(): void
    {
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $more = [$this->component($this->factory, '10', 3), $this->component($this->factory, '20', 3), $this->component($this->factory, '30', 3)];
        $bundleId = (int) $example['bundle']->getId();
        $few = (string) $bundleId;
        $many = $bundleId.','.implode(',', array_map(static fn ($product): int => (int) $product->getId(), $more));

        $small = \count(self::bundleStatements($this->recordSqlQueries(fn () => $this->rowsOf('product', ['id' => $few]))));
        // What the first page warmed is forgotten: the second is a request of its own.
        $this->getService(EffectivePriceCatalog::class)->reset();
        $large = \count(self::bundleStatements($this->recordSqlQueries(fn () => $this->rowsOf('product', ['id' => $many]))));

        self::assertGreaterThan(0, $small);
        self::assertSame($small, $large, 'Three more products on the page cost no read more.');
    }

    public function testAnApiHandOverOfTheCompositionIsJudgedByTheGuardWithoutResolvingItAgain(): void
    {
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $resolver = $this->getService(CartCompositionResolver::class);
        $productId = (int) $example['bundle']->getId();

        $composition = $resolver->resolveAndHandOver($productId, null);
        $handedOver = self::bundleStatements($this->recordSqlQueries(static fn () => $resolver->forAddition($productId, $composition)));
        $again = self::bundleStatements($this->recordSqlQueries(static fn () => $resolver->forAddition($productId, $composition)));

        self::assertSame([], $handedOver, 'The composition the API resolved is not resolved a second time.');
        self::assertNotSame([], $again, 'A hand-over serves once: the next addition of the same composition is judged afresh.');
    }

    public function testTheAvailabilityOfSeveralLinesOfTheSameCompositionIsAskedOnce(): void
    {
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $availability = $this->getService(CartLineAvailability::class);
        $saleElements = ProductSaleElementsQuery::create()->filterByProductId($example['bundle']->getId())->findOne();
        $composition = $this->getService(CartCompositionResolver::class)->resolveAndHandOver((int) $example['bundle']->getId(), null);

        $one = \count($this->recordSqlQueries(static fn () => $availability->forBundleLines([1 => [$saleElements, $composition]])));
        $three = \count($this->recordSqlQueries(static fn () => $availability->forBundleLines([1 => [$saleElements, $composition], 2 => [$saleElements, $composition], 3 => [$saleElements, $composition]])));

        self::assertGreaterThan(0, $one);
        self::assertSame($one, $three);
        self::assertSame([], $availability->forBundleLines([]));
    }

    public function testTheAvailabilityOfLinesOfSeveralBundlesCostsTheQueriesOfOne(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $availability = $this->getService(CartLineAvailability::class);
        $resolver = $this->getService(CartCompositionResolver::class);
        $lines = [];

        foreach ([1, 2, 3] as $key) {
            $bundle = $this->exampleBundle($this->factory, $service)['bundle'];
            $lines[$key] = [
                ProductSaleElementsQuery::create()->filterByProductId($bundle->getId())->findOne(),
                $resolver->resolveAndHandOver((int) $bundle->getId(), null),
            ];
        }

        $one = \count($this->recordSqlQueries(static fn () => $availability->forBundleLines([1 => $lines[1]])));
        $three = \count($this->recordSqlQueries(static fn () => $availability->forBundleLines($lines)));

        self::assertGreaterThan(0, $one);
        self::assertSame($one, $three, 'Three bundles in a cart cost the queries of one.');
    }

    public function testASimpleLineInsertedInAShopWithoutBundleDoesNotReadItsProduct(): void
    {
        $product = $this->component($this->factory, '10', 3);
        $cart = $this->factory->cart(null, ['currency' => Currency::getDefaultCurrency()]);

        $statements = $this->recordSqlQueries(fn () => $this->factory->cartItem($cart, $product));

        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/^\s*SELECT\b.*\bFROM `product`\s+WHERE/is', $statement),
        )), 'No bundle in the shop: no product read to tell a bundle from a simple one.');
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private static function bundleStatements(array $statements): array
    {
        return array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/is_bundle`?\s*=|FROM `product_bundle_item`/i', $statement),
        ));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function rowsOf(string $loop, array $arguments): void
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute($loop, $arguments + [
            'lang' => $lang->getId(),
            'currency' => Currency::getDefaultCurrency()->getId(),
            'visible' => '*',
        ]);

        iterator_to_array($result);
    }
}

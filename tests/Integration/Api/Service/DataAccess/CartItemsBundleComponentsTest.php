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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * What a theme reads to draw the components of a bundle line of the cart: attr('cart', 'cart_items').
 */
final class CartItemsBundleComponentsTest extends ActionIntegrationPromotionTestCase
{
    use BuildsBundles;
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();
        AttributeAccessService::clearCache();
        $this->getService(PricingActivityChecker::class)->reset();
    }

    public function testABundleLineCarriesTheComponentsItRetained(): void
    {
        $locale = LangQuery::create()->filterByByDefault(1)->findOne()->getLocale();
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $example['bikeProduct']->setLocale($locale)->setTitle('Bike')->save();
        $example['helmetProduct']->setLocale($locale)->setTitle('Helmet')->save();
        $cart = $this->newEmptyCart();

        $this->addBundle($cart, $example, 2);
        $simpleLine = $this->addItem($cart, $this->product());
        AttributeAccessService::clearCache();

        $items = $this->getService(AttributeAccessService::class)->attributeCart('cart_items');

        self::assertCount(2, $items);
        self::assertSame(
            [
                ['bundleItemId' => (int) $example['bike']->getId(), 'productSaleElementsId' => (int) $this->saleElementsOf($example['bikeProduct'])->getId(), 'productId' => (int) $example['bikeProduct']->getId(), 'title' => 'Bike', 'quantity' => '1'],
                ['bundleItemId' => (int) $example['helmet']->getId(), 'productSaleElementsId' => (int) $this->saleElementsOf($example['helmetProduct'])->getId(), 'productId' => (int) $example['helmetProduct']->getId(), 'title' => 'Helmet', 'quantity' => '2'],
            ],
            $items[0]['bundle_components'],
            'The lock the buyer left out is not there, and the order is the one of the bundle.',
        );
        self::assertArrayNotHasKey('bundle_components', $items[1], 'A line that is not a bundle is left as it was.');
        self::assertSame((int) $simpleLine->getId(), $items[1]['id']);
    }

    public function testTheCartLoopShowsWhatTheComponentsSupplyAsTheStockOfABundleLine(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $cart = $this->newEmptyCart();
        $this->addBundle($cart, $example, 1);
        $this->addItem($cart, $this->product(['baseQuantity' => 7]));

        $rows = [];

        foreach ($this->getService(LoopExecutor::class)->execute('cart') as $row) {
            $rows[] = $row->getVarVal();
        }

        ConfigQuery::resetCache();
        // min(7, floor(5 / 2)) = 2 bundles; the sale element of the bundle holds nothing.
        self::assertSame(2, $rows[0]['STOCK']);
        self::assertSame(7, (int) $rows[1]['STOCK']);
    }

    public function testTheComponentsOfAllTheBundleLinesAreReadWithoutAQueryPerLine(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $first = $this->exampleBundle($this->factory, $service);
        $second = $this->exampleBundle($this->factory, $service);
        $third = $this->exampleBundle($this->factory, $service);
        $attributes = $this->getService(AttributeAccessService::class);

        $cart = $this->newEmptyCart();
        $this->addBundle($cart, $first, 1);
        AttributeAccessService::clearCache();
        $cart->clearCartItems();
        $statementsForOne = $this->recordSqlQueries(static fn () => $attributes->attributeCart('cart_items'));

        $this->addBundle($cart, $second, 1);
        $this->addBundle($cart, $third, 1);
        AttributeAccessService::clearCache();
        $cart->clearCartItems();
        $items = [];
        $statementsForThree = $this->recordSqlQueries(static function () use ($attributes, &$items): void {
            $items = $attributes->attributeCart('cart_items');
        });

        self::assertCount(3, $items);
        self::assertSame(
            self::countSqlQueriesSelectingFrom($statementsForOne, 'product_bundle_item'),
            self::countSqlQueriesSelectingFrom($statementsForThree, 'product_bundle_item'),
        );
        self::assertSame(
            self::countSqlQueriesSelectingFrom($statementsForOne, 'product_i18n'),
            self::countSqlQueriesSelectingFrom($statementsForThree, 'product_i18n'),
        );
        self::assertSame(
            self::countSqlQueriesSelectingFrom($statementsForOne, 'product_sale_elements'),
            self::countSqlQueriesSelectingFrom($statementsForThree, 'product_sale_elements'),
            implode("\n", array_diff($statementsForThree, $statementsForOne)),
        );
    }

    /**
     * @param array<string, mixed> $example
     */
    private function addBundle(\Thelia\Model\Cart $cart, array $example, int $locks): void
    {
        $event = new \Thelia\Core\Event\Cart\CartEvent($cart);
        $event
            ->setProductId((int) $example['bundle']->getId())
            ->setProductSaleElementsId((int) $this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(false)
            ->setBundleComposition(BundleComposition::fromArray([
                ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
                ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
                ['bundleItemId' => $example['lock']->getId(), 'quantity' => 0],
            ]));

        $this->dispatch($event, \Thelia\Core\Event\TheliaEvents::CART_ADDITEM);
    }
}

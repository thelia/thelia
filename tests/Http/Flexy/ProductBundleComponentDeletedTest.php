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

namespace Thelia\Tests\Http\Flexy;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A merchant deletes the product of a required component of a bundle, the way the back-office does. The sheet of
 * the bundle still answers, says the bundle cannot be bought, and the composition keeps its row without any
 * reference left to the sale element that is gone.
 *
 * The page belongs to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class ProductBundleComponentDeletedTest extends WebIntegrationTestCase
{
    use ForgetsPooledModels;

    /** @var list<string> */
    private array $phpErrors = [];

    private const BUNDLE_URL = 'flexy-product-bundle-deleted-component-test.html';

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();
    }

    public function testDeletingTheProductOfARequiredComponentMakesTheBundleUnavailableWithoutBreakingItsSheet(): void
    {
        $example = $this->bundleUnderTest();
        $requiredItemId = $example['requiredItemId'];

        self::assertStringContainsString('Add to cart', $this->renderPage()->filter('.PseSelector')->text(), 'the bundle is sellable before the deletion');

        $this->getService(EventDispatcherInterface::class)->dispatch(new ProductDeleteEvent($example['requiredProductId']), TheliaEvents::PRODUCT_DELETE);

        // The deletion empties the pool of the sale elements but not the one of their prices, which still point at the old
        // instances: a real request starts with empty pools, so the page is rendered the way a browser would get it.
        self::forgetPooledModels();

        self::assertNull(ProductQuery::create()->findPk($example['requiredProductId']), 'the product of the component is really gone');

        $crawler = $this->renderPage();

        $selector = $crawler->filter('.PseSelector')->text();
        self::assertStringContainsString('This bundle is currently unavailable', $selector);
        self::assertStringNotContainsString('Add to cart', $selector);
        self::assertStringNotContainsString('Page bike', $crawler->filter('[data-testid="bundle-composition"]')->text(), 'the deleted component is no longer listed');
        self::assertStringContainsString('Page lock', $crawler->filter('[data-testid="bundle-composition"]')->text(), 'the other component is still listed');

        // The line stays, and points at nothing: the foreign key sets it to NULL rather than leaving a sale element id that does not exist.
        $item = ProductBundleItemQuery::create()->findPk($requiredItemId);
        self::assertNotNull($item, 'the row of the composition is kept');
        self::assertNull($item->getProductSaleElementsId());

        $orphans = $this->getPropelConnection()->query(
            'SELECT COUNT(*) FROM product_bundle_item item LEFT JOIN product_sale_elements pse ON pse.id = item.product_sale_elements_id'
            .' WHERE item.bundle_product_id = '.(int) $example['bundleId'].' AND item.product_sale_elements_id IS NOT NULL AND pse.id IS NULL',
        )->fetchColumn();
        self::assertSame(0, (int) $orphans, 'no row of the composition points at a sale element that does not exist');
        self::assertNull(ProductSaleElementsQuery::create()->findPk($example['requiredSaleElementId']));
        self::assertSame(1, ProductBundleItemQuery::create()->filterByBundleProductId($example['bundleId'])->filterByProductSaleElementsId(null, Criteria::ISNOTNULL)->count(), 'the optional component keeps its sale element');
        self::assertSame([], $this->phpErrors, 'rendering the sheet raised no PHP warning, notice or deprecation');
    }

    private function renderPage(): Crawler
    {
        // PHPUnit only reports what a page raises (the test stays green), so the errors are collected and asserted.
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            // A statement silenced with @ leaves only the fatal levels in error_reporting(): it stays silenced. The level
            // check cannot be used, the request lowers error_reporting() below the warnings on its own.
            if (0 !== (error_reporting() & ~(\E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR | \E_PARSE))) {
                $this->phpErrors[] = \sprintf('%s (%s:%d)', $message, basename($file), $line);
            }

            return true;
        }, \E_WARNING | \E_NOTICE | \E_DEPRECATED | \E_USER_WARNING | \E_USER_NOTICE | \E_USER_DEPRECATED);

        try {
            $this->assertPageRenders('/'.self::BUNDLE_URL);
        } finally {
            restore_error_handler();
        }

        return new Crawler((string) $this->client->getResponse()->getContent());
    }

    /**
     * A bike (required) and a lock (optional, 0 to 3) priced by the components.
     *
     * @return array{bundleId: int, requiredProductId: int, requiredSaleElementId: int, requiredItemId: int}
     */
    private function bundleUnderTest(): array
    {
        // Built without createFixtureFactory(): that helper pushes a synthetic request that would become the main request of the page.
        $factory = new FixtureFactory($this->getPropelConnection());
        $service = $this->getService(BundleCompositionService::class);
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $bike = $factory->product($category, $taxRule, $currency, ['title' => 'Page bike', 'basePrice' => 500.0, 'baseQuantity' => 7]);
        $lock = $factory->product($category, $taxRule, $currency, ['title' => 'Page lock', 'basePrice' => 20.0, 'baseQuantity' => 10]);
        $bundle = $factory->product($category, $taxRule, $currency, ['title' => 'Page bundle', 'basePrice' => 999.0, 'baseQuantity' => 0]);
        $bundle = $service->setBundleSettings((int) $bundle->getId(), true, BundlePriceMode::Computed, '10', BundleDiscountType::Percent);
        $bundle->setRewrittenUrl('en_US', self::BUNDLE_URL);

        $bikeSaleElementId = (int) $bike->getProductSaleElementss()->getFirst()->getId();
        $required = $service->addItem((int) $bundle->getId(), $bikeSaleElementId, '1');
        $service->addItem((int) $bundle->getId(), (int) $lock->getProductSaleElementss()->getFirst()->getId(), '1', '0', '3', false);

        return [
            'bundleId' => (int) $bundle->getId(),
            'requiredProductId' => (int) $bike->getId(),
            'requiredSaleElementId' => $bikeSaleElementId,
            'requiredItemId' => (int) $required->getId(),
        ];
    }
}

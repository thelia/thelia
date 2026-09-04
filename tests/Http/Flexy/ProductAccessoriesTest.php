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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A merchant picks the accessories of a product in the back-office and orders them there.
 * The core stores that list and the `accessory` loop reads it back in position order; showing
 * it is the theme's part.
 *
 * These tests pin what a shop gets out of that contract: the product page carries the picked
 * accessories in the picked order, an accessory the merchant hid is gone, and a product with
 * none carries no empty strip.
 *
 * The page belongs to the front-office theme, which ships as its own package on its own
 * release cycle: a theme older than the accessories strip is reported as skipped rather than
 * failed.
 */
/*
 * Each test runs in its own process: the front product resource is answered on the first
 * booted kernel of a process only, and a second one reads it back as null — which is a
 * limitation of the test harness, not of the page.
 */
#[RunTestsInSeparateProcesses]
final class ProductAccessoriesTest extends WebIntegrationTestCase
{
    private const PRODUCT_TEMPLATE = 'product.html.twig';

    private const STRIP_TITLE = 'Accessories';

    private const PRODUCT_URL = 'flexy-accessories-test.html';

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();
        $productPage = $frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::PRODUCT_TEMPLATE;

        if (!file_exists($productPage) || !str_contains((string) file_get_contents($productPage), self::STRIP_TITLE)) {
            self::markTestSkipped('The installed front-office theme has no accessories strip.');
        }
    }

    public function testTheProductPageCarriesItsAccessoriesInThePickedOrder(): void
    {
        $this->productWithAccessories();

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString(self::STRIP_TITLE, $content, 'The page must carry the accessories strip.');

        $first = strpos($content, 'Accessory first');
        $second = strpos($content, 'Accessory second');
        $third = strpos($content, 'Accessory third');

        self::assertIsInt($first, 'The accessory at position 1 must be on the page.');
        self::assertIsInt($second, 'The accessory at position 2 must be on the page.');
        self::assertIsInt($third, 'The accessory at position 3 must be on the page.');

        self::assertLessThan($second, $first, 'Accessories follow the position the merchant picked.');
        self::assertLessThan($third, $second, 'Accessories follow the position the merchant picked.');
    }

    /**
     * The strip is placed above the category one: the hand-picked selection comes before the
     * automatic one.
     */
    public function testTheAccessoriesStripComesBeforeTheCategoryOne(): void
    {
        $this->productWithAccessories();

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        $accessories = strpos($content, self::STRIP_TITLE);
        $category = strpos($content, 'In the same category');

        self::assertIsInt($accessories, 'The page must carry the accessories strip.');
        self::assertIsInt($category, 'The page must keep the category strip.');
        self::assertLessThan($category, $accessories);
    }

    public function testAnAccessoryTheMerchantHidIsNotShown(): void
    {
        $accessories = $this->productWithAccessories();

        $accessories['second']->setVisible(0)->save($this->getPropelConnection());

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('Accessory second', $content);
        self::assertStringContainsString('Accessory first', $content);
        self::assertStringContainsString('Accessory third', $content);
    }

    /**
     * Nothing to show is not an empty strip: a product with no accessory carries no title and
     * keeps the category strip it always had.
     */
    public function testAProductWithoutAccessoriesCarriesNoStrip(): void
    {
        $factory = $this->factory();
        $product = $this->product($factory, $factory->category(), 'Product page under test');
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString(self::STRIP_TITLE, $content);
        self::assertStringContainsString('In the same category', $content);
    }

    /**
     * @return array<string, Product> the accessories, keyed by the position they were picked at
     */
    private function productWithAccessories(): array
    {
        $factory = $this->factory();

        $product = $this->product($factory, $factory->category(), 'Product page under test');
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        // The accessories live in their own category so the category strip of the page cannot
        // list them too, which would say nothing about the order of the accessories strip.
        $shelf = $factory->category();

        $accessories = [
            'third' => $this->product($factory, $shelf, 'Accessory third'),
            'first' => $this->product($factory, $shelf, 'Accessory first'),
            'second' => $this->product($factory, $shelf, 'Accessory second'),
        ];

        // Picked out of order on purpose: neither the insertion order nor the id order is the
        // order the merchant asked for.
        $factory->accessory($product, $accessories['third'], 3);
        $factory->accessory($product, $accessories['first'], 1);
        $factory->accessory($product, $accessories['second'], 2);

        return $accessories;
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render below — the
     * one the session, and therefore the current language, is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function product(FixtureFactory $factory, Category $category, string $title): Product
    {
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());

        $product->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $product;
    }
}

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

use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A merchant relates products to one another under a type, and the product page
 * offers one block per type that has something under it.
 *
 * These tests pin what a shop gets out of that: the blocks the merchant filled
 * are there, in the order the types are arranged, a type nothing was picked
 * under leaves no empty title behind, and a product taken offline is offered by
 * no block at all.
 *
 * The page belongs to the front-office theme, which ships as its own package on
 * its own release cycle: a theme older than the blocks is reported as skipped
 * rather than failed.
 */
final class ProductRelationBlocksTest extends WebIntegrationTestCase
{
    private const PRODUCT_TEMPLATE = 'product.html.twig';

    private const TYPES_ENDPOINT = '/api/front/product_association_types';

    private const PRODUCT_URL = 'flexy-relation-blocks-test.html';

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();
        $productPage = $frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::PRODUCT_TEMPLATE;

        if (!file_exists($productPage) || !str_contains((string) file_get_contents($productPage), self::TYPES_ENDPOINT)) {
            self::markTestSkipped('The installed front-office theme has no relation blocks.');
        }
    }

    public function testEachFilledTypeCarriesItsOwnBlockInTheOrderOfTheTypes(): void
    {
        $product = $this->productUnderTest();
        $shelf = $this->factory()->category();

        $this->relate($product, $this->product($shelf, 'A picked accessory'), ProductAssociationType::CODE_ACCESSORY, 1);
        $this->relate($product, $this->product($shelf, 'A complementary pick'), ProductAssociationType::CODE_CROSS_SELLING, 1);

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        $accessories = strpos($content, $this->wordingOf(ProductAssociationType::CODE_ACCESSORY));
        $complementary = strpos($content, $this->wordingOf(ProductAssociationType::CODE_CROSS_SELLING));

        self::assertIsInt($accessories, 'The block of a type the merchant filled is on the page.');
        self::assertIsInt($complementary, 'The block of a type the merchant filled is on the page.');
        self::assertStringContainsString('A picked accessory', $content);
        self::assertStringContainsString('A complementary pick', $content);

        // The accessory type is arranged before the cross-selling one, and the
        // blocks follow the types rather than the order they were written in.
        self::assertLessThan($complementary, $accessories, 'The blocks follow the order the types are arranged in.');
    }

    /**
     * Nothing picked under a type is not an empty block: no title, and the
     * category strip the page always had stays where it is.
     */
    public function testATypeWithNothingUnderItCarriesNoBlock(): void
    {
        $product = $this->productUnderTest();

        $this->relate($product, $this->product($this->factory()->category(), 'A picked accessory'), ProductAssociationType::CODE_ACCESSORY, 1);

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString($this->wordingOf(ProductAssociationType::CODE_ACCESSORY), $content);
        self::assertStringNotContainsString(
            $this->wordingOf(ProductAssociationType::CODE_UP_SELLING),
            $content,
            'A type nothing was picked under leaves no title behind.',
        );
        self::assertStringContainsString('In the same category', $content);
    }

    public function testAProductTakenOfflineIsOfferedByNoBlock(): void
    {
        $product = $this->productUnderTest();
        $shelf = $this->factory()->category();

        $offline = $this->product($shelf, 'A product taken offline');
        $offline->setVisible(0)->save($this->getPropelConnection());

        $this->relate($product, $offline, ProductAssociationType::CODE_ACCESSORY, 1);
        $this->relate($product, $this->product($shelf, 'A product still offered'), ProductAssociationType::CODE_ACCESSORY, 2);

        $this->assertPageRenders('/'.self::PRODUCT_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('A product taken offline', $content);
        self::assertStringContainsString('A product still offered', $content);
    }

    private function productUnderTest(): Product
    {
        $product = $this->product($this->factory()->category(), 'Product page under test');
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        return $product;
    }

    private function relate(Product $product, Product $related, string $typeCode, int $position): void
    {
        $this->factory()->association($product, $related, $typeCode, $position);
    }

    /**
     * The block is titled with the wording of its type, so the test reads it
     * from the type rather than repeating the seed.
     */
    private function wordingOf(string $typeCode): string
    {
        $type = ProductAssociationTypeQuery::create()->findOneByCode($typeCode);

        self::assertInstanceOf(ProductAssociationType::class, $type, \sprintf('The install seeds the "%s" type.', $typeCode));

        $type->setLocale('en_US');

        return (string) $type->getTitle();
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

    private function product(Category $category, string $title): Product
    {
        $factory = $this->factory();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());

        $product->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $product;
    }
}

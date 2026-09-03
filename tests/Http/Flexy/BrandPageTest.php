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
use Thelia\Model\Brand;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A brand carries a rewritten url, and that url names the `brand` view. Serving it is the
 * theme's part: the core resolves the url, publishes `brand_id` in the query and turns down a
 * brand that is not visible through {@see \Thelia\Action\Brand::viewCheck()}.
 *
 * These tests pin what a shop gets out of that contract: the page of a visible brand lists its
 * products, and a brand the merchant hid cannot be told apart from one that never existed.
 *
 * The page belongs to the front-office theme, which ships as its own package on its own
 * release cycle: a theme older than the brand page is reported as skipped rather than failed.
 */
final class BrandPageTest extends WebIntegrationTestCase
{
    private const BRAND_TEMPLATE = 'brand.html.twig';

    private const BRAND_URL = 'flexy-brand-page-test.html';

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();

        if (!file_exists($frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::BRAND_TEMPLATE)) {
            self::markTestSkipped('The installed front-office theme has no brand page.');
        }
    }

    public function testTheUrlOfABrandServesItsPage(): void
    {
        $brand = $this->brandWithAProduct();

        $this->assertPageRenders('/'.self::BRAND_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString($brand->getTitle(), $content, 'The brand page must carry the brand title.');
        self::assertStringContainsString('Brand page product', $content, 'The brand page must list the products of the brand.');
        self::assertStringContainsString('aria-current="page"', $content, 'The brand page must carry a breadcrumb naming it.');
    }

    /**
     * A brand with nothing to show is still a page: the merchant published it, and the listing
     * says it is empty instead of answering an error.
     */
    public function testABrandWhoseProductsAreAllHiddenStillHasItsPage(): void
    {
        $this->brandWithAProduct(['visible' => 0]);

        $this->assertPageRenders('/'.self::BRAND_URL);

        self::assertStringNotContainsString(
            'Brand page product',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    /**
     * The brand carries a title in the shop's default language only, and the page is asked for
     * in another one: the fallback title is served rather than an empty heading.
     */
    public function testABrandWithNoTitleInTheCurrentLanguageFallsBackOnItsDefaultOne(): void
    {
        $brand = $this->brandWithAProduct();
        $brand->setRewrittenUrl('fr_FR', 'fr-'.self::BRAND_URL);

        $this->assertPageRenders('/fr-'.self::BRAND_URL);

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('<html lang="fr"', $content, 'The french url must serve the page in french.');
        self::assertStringContainsString($brand->setLocale('en_US')->getTitle(), $content);
    }

    public function testTheUrlOfAHiddenBrandIsNotFound(): void
    {
        $brand = $this->brandWithAProduct();
        $brand->setVisible(0)->save($this->getPropelConnection());

        $this->client->request('GET', '/'.self::BRAND_URL);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The 404 of a hidden brand must not be told apart from the one of a brand that does not
     * exist: a distinguishable answer enumerates the brands the shop keeps to itself.
     */
    public function testAHiddenBrandAnswersTheSameAsAnUnknownUrl(): void
    {
        $this->client->request('GET', '/'.self::BRAND_URL);
        $unknown = $this->client->getResponse();

        $brand = $this->brandWithAProduct();
        $brand->setVisible(0)->save($this->getPropelConnection());

        $this->client->request('GET', '/'.self::BRAND_URL);
        $hidden = $this->client->getResponse();

        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame($unknown->getStatusCode(), $hidden->getStatusCode());
        self::assertSame($unknown->getContent(), $hidden->getContent());
    }

    /**
     * A single-segment url ending with a slash is not the rewritten url of anything, and it
     * must not fall through to the theme template whose name matches the segment. Every
     * rewritten url of the catalogue behaves this way, the brand one included.
     */
    public function testTheUrlOfABrandWithATrailingSlashIsNotFound(): void
    {
        $this->brandWithAProduct();

        $this->client->request('GET', '/'.self::BRAND_URL.'/');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * The page is reached through the rewritten url of a brand, which is what names the brand
     * to show. Asking for the view by its own name names none, and browsing the template with
     * no brand at all is not a page.
     */
    public function testTheBrandViewIsNotServedWithoutABrand(): void
    {
        $this->client->request('GET', '/brand');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    private function brandWithAProduct(array $productOverrides = []): Brand
    {
        $factory = $this->factory();

        $brand = $factory->brand(['title' => 'Brand page brand']);
        $brand->setRewrittenUrl('en_US', self::BRAND_URL);

        $this->product($factory, $productOverrides)
            ->setBrandId($brand->getId())
            ->save($this->getPropelConnection());

        return $brand;
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

    private function product(FixtureFactory $factory, array $overrides = []): Product
    {
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            $overrides,
        );

        return $product->setLocale('en_US')->setTitle('Brand page product');
    }
}

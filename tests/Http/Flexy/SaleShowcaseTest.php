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
use Thelia\Model\Sale;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A reserved-sale operation carries a rewritten url naming the `sale` view, the way a brand
 * or a category does. Serving its page — title, countdown, product grid — is the theme's
 * part; the core resolves the url, publishes `sale_id` in the query, and decides who gets to
 * see the operation at all through {@see \Thelia\Domain\Sale\ReservedSaleVisibility}.
 *
 * These tests pin what a shop gets out of that contract: a public operation's page lists its
 * products and shows a countdown exactly when its settings ask for one, a reserved operation
 * an anonymous visitor is not named on answers 404 rather than a page with nothing on it, and
 * a hidden drop's products stay off a listing page the same way they stay off the API.
 *
 * The page belongs to the front-office theme, which ships as its own package on its own
 * release cycle: a theme older than the sale showcase is reported as skipped rather than
 * failed.
 */
final class SaleShowcaseTest extends WebIntegrationTestCase
{
    private const SALE_TEMPLATE = 'sale.html.twig';

    /** Present in the template only once the showcase component is wired in. */
    private const SHOWCASE_MARKER = 'Layouts:SaleShowcase';

    /** Present in the rendered page only while the countdown component is on it. */
    private const COUNTDOWN_MARKER = 'Molecules--Countdown--base';

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();
        $salePage = $frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::SALE_TEMPLATE;

        if (!file_exists($salePage) || !str_contains((string) file_get_contents($salePage), self::SHOWCASE_MARKER)) {
            self::markTestSkipped('The installed front-office theme has no reserved-sale showcase page.');
        }
    }

    public function testAPublicOperationPageCarriesItsTitleAndProducts(): void
    {
        $factory = $this->factory();
        $product = $this->product($factory, $factory->category(), 'Sale showcase product');

        $sale = $this->runningSale($factory, ['title' => 'Winter drop']);
        $sale->setRewrittenUrl('en_US', 'flexy-sale-showcase-test.html');
        $factory->saleProduct($sale, $product);

        $this->assertPageRenders('/flexy-sale-showcase-test.html');

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Winter drop', $content, 'The page must carry the operation title.');
        self::assertStringContainsString('Sale showcase product', $content, 'The page must list the products of the operation.');
    }

    public function testTheCountdownShowsWhenTheOperationAsksForIt(): void
    {
        $factory = $this->factory();
        $product = $this->product($factory, $factory->category(), 'Countdown-on product');

        $sale = $this->runningSale($factory, ['countdownMode' => Sale::COUNTDOWN_MODE_FROM_OPENING]);
        $sale->setRewrittenUrl('en_US', 'flexy-sale-countdown-on-test.html');
        $factory->saleProduct($sale, $product);

        $this->assertPageRenders('/flexy-sale-countdown-on-test.html');

        self::assertStringContainsString(
            self::COUNTDOWN_MARKER,
            (string) $this->client->getResponse()->getContent(),
            'A running countdown must render the Countdown component.',
        );
    }

    public function testTheCountdownIsAbsentWhenTheOperationDoesNotAskForIt(): void
    {
        $factory = $this->factory();
        // countdownMode defaults to Sale::COUNTDOWN_MODE_NONE.
        $product = $this->product($factory, $factory->category(), 'Countdown-off product');

        $sale = $this->runningSale($factory);
        $sale->setRewrittenUrl('en_US', 'flexy-sale-countdown-off-test.html');
        $factory->saleProduct($sale, $product);

        $this->assertPageRenders('/flexy-sale-countdown-off-test.html');

        self::assertStringNotContainsString(
            self::COUNTDOWN_MARKER,
            (string) $this->client->getResponse()->getContent(),
            'A mode-NONE operation must not show a countdown.',
        );
    }

    public function testAProductInARunningSaleCarriesItsLabelOnItsListingCard(): void
    {
        $factory = $this->factory();
        $category = $this->category($factory);
        $product = $this->product($factory, $category, 'Listing sale product');
        $category->setRewrittenUrl('en_US', 'flexy-sale-listing-test.html');

        $sale = $this->runningSale($factory, ['saleLabel' => 'LISTING-SALE-TAG']);
        $factory->saleProduct($sale, $product);

        $this->assertPageRenders('/flexy-sale-listing-test.html');

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Listing sale product', $content);
        self::assertStringContainsString('LISTING-SALE-TAG', $content, 'The card must carry the operation label.');
        self::assertStringContainsString('Tag--sale', $content, 'The label must render as the sale variant of Molecules:Tag.');
    }

    /**
     * `Sale::active` is maintained by a scheduled command that can lag behind the clock: an
     * operation left flagged active past its own end date must not keep advertising a label
     * that no longer means anything.
     */
    public function testAProductInAnOperationPastItsEndDateCarriesNoLabelOnItsListingCard(): void
    {
        $factory = $this->factory();
        $category = $this->category($factory);
        $product = $this->product($factory, $category, 'Stale sale product');
        $category->setRewrittenUrl('en_US', 'flexy-sale-stale-listing-test.html');

        $sale = $this->runningSale($factory, [
            'saleLabel' => 'STALE-SALE-TAG',
            'endDate' => new \DateTime('-1 hour'),
        ]);
        $factory->saleProduct($sale, $product);

        $this->assertPageRenders('/flexy-sale-stale-listing-test.html');

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Stale sale product', $content);
        self::assertStringNotContainsString(
            'STALE-SALE-TAG',
            $content,
            'An operation whose end date has passed must not keep its label, even while still flagged active.',
        );
    }

    /**
     * A reserved operation not open to the visitor answers the same as a missing one: a 404,
     * never a page rendered with nothing worth showing on it.
     */
    public function testAnAnonymousVisitorGetsA404OnAReservedOperation(): void
    {
        $factory = $this->factory();
        $product = $this->product($factory, $factory->category(), 'Reserved product');

        $sale = $this->runningSale($factory, ['audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);
        $sale->setRewrittenUrl('en_US', 'flexy-sale-reserved-test.html');
        $factory->saleProduct($sale, $product);

        $this->client->request('GET', '/flexy-sale-reserved-test.html');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testProductsOfAHiddenReservedOperationAreAbsentFromAListingPage(): void
    {
        $factory = $this->factory();
        $category = $this->category($factory);
        $hiddenProduct = $this->product($factory, $category, 'Hidden drop product');
        $category->setRewrittenUrl('en_US', 'flexy-sale-hidden-listing-test.html');

        $sale = $this->runningSale($factory, [
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $factory->saleProduct($sale, $hiddenProduct);
        $factory->saleCustomer($sale, $factory->customer($factory->customerTitle()));

        $this->assertPageRenders('/flexy-sale-hidden-listing-test.html');

        self::assertStringNotContainsString(
            'Hidden drop product',
            (string) $this->client->getResponse()->getContent(),
            'A hidden reserved operation must keep its products off an anonymous listing.',
        );
    }

    private function runningSale(FixtureFactory $factory, array $overrides = []): Sale
    {
        return $factory->sale($overrides + [
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
            'saleLabel' => $overrides['saleLabel'] ?? 'SALE-LABEL',
        ]);
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

    /**
     * The listing page's Subheader requires a title: a bare factory->category() carries no
     * i18n row at all, and the page would 500 rather than render.
     */
    private function category(FixtureFactory $factory): Category
    {
        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Sale listing test category')->save($this->getPropelConnection());

        return $category;
    }
}

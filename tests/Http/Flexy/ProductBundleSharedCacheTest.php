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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The sheet of a bundle stays as cacheable as the sheet of an ordinary product for a visitor who chose nothing: no
 * session cookie, and the same cache headers, so a proxy in front of the shop keeps it.
 *
 * The page belongs to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class ProductBundleSharedCacheTest extends WebIntegrationTestCase
{
    private const BUNDLE_URL = 'bundle-shared-cache-page.html';

    private const PLAIN_URL = 'bundle-shared-cache-plain-page.html';

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheSheetOfABundleStartsNoSessionAndIsCachedLikeTheSheetOfAProductWhenSharingIsOn(): void
    {
        $bundleProduct = $this->pages();
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);

        $plain = $this->anonymousVisit(self::PLAIN_URL);
        $bundle = $this->anonymousVisit(self::BUNDLE_URL);

        self::assertSame([], $plain['cookies'], 'the reference sheet starts no session');
        self::assertSame([], $bundle['cookies'], 'the sheet of a bundle starts no session');
        self::assertFalse($bundle['setCookie'], 'no Set-Cookie on the sheet of a bundle');
        self::assertSame($plain['cacheControl'], $bundle['cacheControl'], 'same Cache-Control as the ordinary sheet');
        self::assertContains('public', $this->directives($bundle['cacheControl']), 'the shared page is public, as the ordinary one');
        self::assertContains('product_bundle_items', $bundle['keys'], 'the page is tagged with the key a change of a product purges');
        self::assertContains('product-'.$bundleProduct->getId(), $bundle['keys'], 'the page is tagged with the product of the bundle');
    }

    public function testTheSheetOfABundleSetsNoMoreCookiesThanTheSheetOfAProductWhenSharingIsOff(): void
    {
        $this->pages();

        $plain = $this->anonymousVisit(self::PLAIN_URL);
        $bundle = $this->anonymousVisit(self::BUNDLE_URL);

        self::assertSame($plain['cookies'], $bundle['cookies'], 'the sheet of a bundle sets the cookies of the ordinary sheet, no more');
        self::assertSame($plain['setCookie'], $bundle['setCookie']);
        self::assertSame($plain['cacheControl'], $bundle['cacheControl'], 'same Cache-Control as the ordinary sheet');
    }

    /**
     * @return array{cookies: list<string>, setCookie: bool, cacheControl: string, keys: list<string>}
     */
    private function anonymousVisit(string $url): array
    {
        $this->client->getCookieJar()->clear();

        // The helpers that build fixtures leave a request without a session on the stack: it would be taken for
        // the main request of the page render.
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }

        $this->assertPageRenders('/'.$url);
        $response = $this->client->getResponse();

        return [
            'cookies' => array_map(static fn ($cookie): string => $cookie->getName(), $response->headers->getCookies()),
            'setCookie' => $response->headers->has('Set-Cookie'),
            'cacheControl' => (string) $response->headers->get('Cache-Control'),
            'keys' => explode(' ', (string) $response->headers->get('xkey')),
        ];
    }

    /**
     * @return list<string>
     */
    private function directives(string $cacheControl): array
    {
        return array_map(static fn (string $part): string => trim(explode('=', $part)[0]), explode(',', $cacheControl));
    }

    private function pages(): Product
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $service = $this->getService(BundleCompositionService::class);
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $plain = $factory->product($category, $taxRule, $currency, ['title' => 'Cache plain product', 'basePrice' => 50.0, 'baseQuantity' => 5]);
        $plain->setRewrittenUrl('en_US', self::PLAIN_URL);

        $bike = $factory->product($category, $taxRule, $currency, ['title' => 'Cache bike', 'basePrice' => 500.0, 'baseQuantity' => 7]);
        $helmet = $factory->product($category, $taxRule, $currency, ['title' => 'Cache helmet', 'basePrice' => 30.0, 'baseQuantity' => 5]);
        $bundle = $factory->product($category, $taxRule, $currency, ['title' => 'Cache bundle', 'basePrice' => 999.0, 'baseQuantity' => 0]);
        $bundle = $service->setBundleSettings((int) $bundle->getId(), true, BundlePriceMode::Computed, '10', BundleDiscountType::Percent);
        $bundle->setRewrittenUrl('en_US', self::BUNDLE_URL);
        $service->addItem((int) $bundle->getId(), (int) $bike->getProductSaleElementss()->getFirst()->getId(), '1');
        $service->addItem((int) $bundle->getId(), (int) $helmet->getProductSaleElementss()->getFirst()->getId(), '2');

        return $bundle;
    }
}

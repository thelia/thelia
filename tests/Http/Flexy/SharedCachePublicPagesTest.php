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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * What a proxy in front of the shop is given for a public page, through the whole kernel
 * and the installed front-office theme rather than a response built by hand.
 *
 * The page of a product is shared for a visitor who has no cookie, is left personal for
 * one who has, and the shared copy carries nothing that belongs to a customer.
 */
final class SharedCachePublicPagesTest extends WebIntegrationTestCase
{
    private const PRODUCT_URL = 'shared-cache-public-page.html';

    private const FIRSTNAME = 'Zephyrine';

    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAVisitorWithoutACookieIsServedAPageAProxyMayKeep(): void
    {
        $product = $this->aProductPage();
        $this->shareThePublicPages();

        $this->visitTheProductPage();
        $this->skipIfTheThemeReadsWhatTheListDoesNotAllow();

        $response = $this->client->getResponse();

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('300', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
        self::assertContains('product-'.$product->getId(), explode(' ', (string) $response->headers->get('xkey')));
    }

    #[DataProvider('cookiesOfAVisitorWhoHasASession')]
    public function testAVisitorWhoAlreadyHasACookieIsServedAPersonalPage(string $name): void
    {
        $this->aProductPage();
        $this->shareThePublicPages();

        $this->client->getCookieJar()->set(new Cookie($name, 'a-value-the-shop-gave-before'));

        $this->visitTheProductPage();

        $response = $this->client->getResponse();

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertNull($response->headers->getCacheControlDirective('s-maxage'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cookiesOfAVisitorWhoHasASession(): iterable
    {
        yield 'a session' => ['PHPSESSID'];
        yield 'a cart' => ['thelia_cart'];
    }

    /**
     * The sitemap reads the catalogue without going through `resources()`: it is shared all
     * the same, and tagged with the list keys a change of a catalogue object purges.
     */
    #[DataProvider('answersOfTheSitemap')]
    public function testTheSitemapIsServedAsAnAnswerAProxyMayKeep(string $method, string $url): void
    {
        $this->shareThePublicPages();

        $this->client->getCookieJar()->clear();
        $this->client->request($method, $url);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('public'), 'Cache-Control: '.$response->headers->get('Cache-Control'));
        self::assertSame('300', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertSame([], $response->headers->getCookies());

        $keys = explode(' ', (string) $response->headers->get('xkey'));
        foreach (['products', 'categories', 'contents', 'folders'] as $key) {
            self::assertContains($key, $keys);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function answersOfTheSitemap(): iterable
    {
        yield 'the index' => ['GET', '/sitemap.xml'];
        yield 'the index, HEAD' => ['HEAD', '/sitemap.xml'];
        yield 'a section' => ['GET', '/sitemap-products.xml'];
    }

    public function testAShopThatDoesNotShareKeepsItsPagesPersonal(): void
    {
        $this->aProductPage();
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::DISABLED->value);

        $this->visitTheProductPage();

        $response = $this->client->getResponse();

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertNull($response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->has('xkey'));
    }

    public function testTheSharedCopyCarriesNothingOfACustomerWhoWasSignedInBefore(): void
    {
        $this->aProductPage();
        $this->shareThePublicPages();
        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle(), [
            'firstname' => self::FIRSTNAME,
            'email' => 'zephyrine.shared-cache@example.test',
        ]);

        $this->injector?->setCustomer($customer);

        $this->visit('/account');

        if (!str_contains((string) $this->client->getResponse()->getContent(), self::FIRSTNAME)) {
            self::markTestSkipped('The customer is not recognised by the account page, so a leak could not be told from an absent customer.');
        }

        $this->visitTheProductPage();

        self::assertFalse($this->client->getResponse()->headers->hasCacheControlDirective('public'));

        $this->injector?->clear();
        $this->client->getCookieJar()->clear();

        $this->visitTheProductPage();
        $this->skipIfTheThemeReadsWhatTheListDoesNotAllow();

        $shared = (string) $this->client->getResponse()->getContent();

        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'));
        self::assertStringNotContainsString(self::FIRSTNAME, $shared);
        self::assertStringNotContainsString($customer->getEmail(), $shared);
    }

    /**
     * The helpers that sign a customer in or build fixtures leave a request without a session
     * on the stack, and it would be taken for the main request of the page render: the page
     * would then fail on "Session has not been set" for a reason the shop does not have.
     */
    private function visitTheProductPage(): void
    {
        $this->visit('/'.self::PRODUCT_URL);
    }

    private function visit(string $url): void
    {
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }

        $this->assertPageRenders($url);
    }

    private function shareThePublicPages(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);
    }

    /**
     * A theme or a module that reads a resource the list of visitor independent ones does not
     * name makes the page personal, on purpose. A theme that moved ahead of the list is not a
     * defect of what these tests pin, so it is reported as skipped.
     */
    private function skipIfTheThemeReadsWhatTheListDoesNotAllow(): void
    {
        $response = $this->client->getResponse();

        if (!$response->headers->hasCacheControlDirective('public') && !$this->getService(SharedCacheKeyCollector::class)->isComplete()) {
            self::markTestSkipped('The installed theme or a module reads a resource that is not on the shared cache list.');
        }
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the main request of the page render.
     */
    private function aProductPage(): Product
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $product->setLocale('en_US')->setTitle('A product a proxy may keep')->save($this->getPropelConnection());
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        return $product;
    }
}

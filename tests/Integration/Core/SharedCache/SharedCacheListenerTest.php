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

namespace Thelia\Tests\Integration\Core\SharedCache;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCacheListener;
use Thelia\Core\SharedCache\SharedCacheMode;
use Thelia\Core\SharedCache\SharedCachePolicy;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class SharedCacheListenerTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAnAnonymousPublicPageBecomesShareableAndLosesItsCookies(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('s-maxage=300', $cacheControl);
        self::assertStringNotContainsString('private', $cacheControl);
        self::assertStringNotContainsString('must-revalidate', $cacheControl);
        self::assertStringNotContainsString('max-age=0', $cacheControl);
        self::assertFalse($response->headers->has('Expires'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function testAHeadRequestIsShareableToo(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();

        $this->handle($this->request('/', 'index', 'HEAD'), $response);

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testTheLifetimeComesFromTheShopConfiguration(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        ConfigQuery::write(SharedCachePolicy::TTL_CONFIG_KEY, '600');
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame('600', $response->headers->getCacheControlDirective('s-maxage'));
    }

    #[DataProvider('invalidLifetimes')]
    public function testAnInvalidLifetimeFallsBackToTheDefault(string $configured): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        ConfigQuery::write(SharedCachePolicy::TTL_CONFIG_KEY, $configured);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame('300', $response->headers->getCacheControlDirective('s-maxage'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLifetimes(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'above the ceiling' => ['999999'];
        yield 'not a number' => ['often'];
    }

    public function testAnnouncingOnlyNeverMakesAResponseThatSetsCookiesPublic(): void
    {
        $this->mode(SharedCacheMode::ANNOUNCE);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame('max-age=300', $response->headers->get('Surrogate-Control'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertNotSame([], $response->headers->getCookies());
    }

    public function testADisabledShopIsLeftAsItWas(): void
    {
        $this->mode(SharedCacheMode::DISABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAnUnknownModeIsTreatedAsDisabled(): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, 'everything');
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAVisitorWhoAlreadySendsACookieKeepsAPersonalResponse(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $request = $this->request('/horatio.html', 'product');
        $request->cookies->set('thelia_cart', 'abc');
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($request, $response);

        self::assertSame($before, $this->snapshot($response));
    }

    #[DataProvider('pagesThatStayPersonal')]
    public function testAPageThatCanHoldPersonalDataStaysPersonal(string $path, string $view): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request($path, $view), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pagesThatStayPersonal(): iterable
    {
        yield 'login view' => ['/login', 'login'];
        yield 'contact view' => ['/contact', 'contact'];
        yield 'account view' => ['/account', 'account'];
        yield 'cart under a whitelisted view' => ['/checkout/cart', 'index'];
        yield 'password reset' => ['/password-sent', 'index'];
        yield 'registration helper' => ['/register/checkVatNumber', 'index'];
        yield 'live component' => ['/_components/Organisms:CartButton:Base', 'index'];
        yield 'api' => ['/api/front/products', 'product'];
        yield 'admin' => ['/admin/products', 'product'];
        yield 'no view at all' => ['/horatio.html', ''];
    }

    public function testAPostRequestIsNeverShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request('/', 'index', 'POST'), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    #[DataProvider('statuses')]
    public function testOnlyASuccessfulAnswerIsShared(int $status): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $response->setStatusCode($status);
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function statuses(): iterable
    {
        yield 'redirect' => [301];
        yield 'not found' => [404];
        yield 'server error' => [500];
    }

    public function testARequestCarryingCredentialsIsNeverShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $request = $this->request('/horatio.html', 'product');
        $request->headers->set('Authorization', 'Bearer token');
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($request, $response);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAnAjaxRequestIsNeverShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $request = $this->request('/horatio.html', 'product');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($request, $response);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAResponseThatForbidsStoringIsNeverShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $response->headers->set('Cache-Control', 'no-store');
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAPriceThatDependsOnTheVisitorKeepsThePageApart(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $checker = $this->createStub(PricingActivityChecker::class);
        $checker->method('hasVisitorDependentPricing')->willReturn(true);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response, $checker);

        self::assertSame($before, $this->snapshot($response));
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle($this->request('/horatio.html', 'product'), $response, null, HttpKernelInterface::SUB_REQUEST);

        self::assertSame($before, $this->snapshot($response));
    }

    #[DataProvider('announcingModes')]
    public function testAPageAnnouncesWhatItReadToAProxyThatIsNotNamed(SharedCacheMode $mode): void
    {
        $this->mode($mode);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response, null, HttpKernelInterface::MAIN_REQUEST, $this->readingAProduct());

        self::assertSame('thelia, product-42, categories, brand-3', $response->headers->get('Surrogate-Key'));
        self::assertSame('thelia product-42 categories brand-3', $response->headers->get('xkey'));
    }

    #[DataProvider('announcingModes')]
    public function testVarnishReadsTheXkeyHeaderOnly(SharedCacheMode $mode): void
    {
        $this->mode($mode);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response, null, HttpKernelInterface::MAIN_REQUEST, $this->readingAProduct(), 'varnish');

        self::assertSame('thelia product-42 categories brand-3', $response->headers->get('xkey'));
        self::assertFalse($response->headers->has('Surrogate-Key'));
    }

    #[DataProvider('announcingModes')]
    public function testSouinReadsTheSurrogateKeyHeaderOnly(SharedCacheMode $mode): void
    {
        $this->mode($mode);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response, null, HttpKernelInterface::MAIN_REQUEST, $this->readingAProduct(), 'souin');

        self::assertSame('thelia, product-42, categories, brand-3', $response->headers->get('Surrogate-Key'));
        self::assertFalse($response->headers->has('xkey'));
    }

    public function testADisabledSharedCacheTagsNothing(): void
    {
        $this->mode(SharedCacheMode::DISABLED);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response, null, HttpKernelInterface::MAIN_REQUEST, $this->readingAProduct());

        self::assertFalse($response->headers->has('Surrogate-Key'));
        self::assertFalse($response->headers->has('xkey'));
    }

    #[DataProvider('announcingModes')]
    public function testAShareablePageVariesOnTheHeadersTheProxyForwards(SharedCacheMode $mode): void
    {
        $this->mode($mode);
        $response = $this->sessionResponse();

        $this->handle($this->request('/horatio.html', 'product'), $response);

        self::assertEqualsCanonicalizing(['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Host'], $response->getVary());
    }

    public function testAPageThatIsNotSharedKeepsItsVary(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $request = $this->request('/horatio.html', 'product');
        $request->cookies->set('PHPSESSID', 'abc');

        $this->handle($request, $response);

        self::assertSame([], $response->getVary());
    }

    private function readingAProduct(): SharedCacheKeyCollector
    {
        return $this->collector('/api/front/products/42', '/api/front/categories', '/api/front/brands/3');
    }

    /**
     * @return iterable<string, array{SharedCacheMode}>
     */
    public static function announcingModes(): iterable
    {
        yield 'announce' => [SharedCacheMode::ANNOUNCE];
        yield 'enabled' => [SharedCacheMode::ENABLED];
    }

    /**
     * @return iterable<string, array{SharedCacheMode}>
     */
    public static function everyMode(): iterable
    {
        foreach (SharedCacheMode::cases() as $mode) {
            yield $mode->value => [$mode];
        }
    }

    public function testAPageThatReadNothingStillCarriesTheKeyOfTheWholeShop(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();

        $this->handle($this->request('/', 'index'), $response);

        self::assertSame('thelia', $response->headers->get('Surrogate-Key'));
        self::assertSame('thelia', $response->headers->get('xkey'));
    }

    #[DataProvider('pagesThatStayPersonal')]
    public function testAPersonalPageAnnouncesNothing(string $path, string $view): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();

        $this->handle($this->request($path, $view), $response, null, HttpKernelInterface::MAIN_REQUEST, $this->collector('/api/front/products/42'));

        self::assertFalse($response->headers->has('Surrogate-Key'));
    }

    public function testAPageThatReadSomethingPersonalIsNotShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);

        $this->handle(
            $this->request('/horatio.html', 'product'),
            $response,
            null,
            HttpKernelInterface::MAIN_REQUEST,
            $this->collector('/api/front/products/42', '/api/front/account/customers/7'),
        );

        self::assertSame($before, $this->snapshot($response));
    }

    public function testAPageThatReadTooManyThingsIsNotShared(): void
    {
        $this->mode(SharedCacheMode::ENABLED);
        $response = $this->sessionResponse();
        $before = $this->snapshot($response);
        $paths = [];
        for ($id = 1; $id <= SharedCacheKeyCollector::MAX_KEYS + 1; ++$id) {
            $paths[] = '/api/front/products/'.$id;
        }

        $this->handle(
            $this->request('/salon.html', 'category'),
            $response,
            null,
            HttpKernelInterface::MAIN_REQUEST,
            $this->collector(...$paths),
        );

        self::assertSame($before, $this->snapshot($response));
    }

    private function collector(string ...$paths): SharedCacheKeyCollector
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);
        foreach ($paths as $path) {
            $collector->record($path);
        }

        return $collector;
    }

    #[DataProvider('everyCase')]
    public function testNoPublicResponseEverSetsACookie(string $mode, string $path, string $view, string $method, bool $withIncomingCookie): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, $mode);
        $request = $this->request($path, $view, $method);
        if ($withIncomingCookie) {
            $request->cookies->set('PHPSESSID', 'abc');
        }
        $response = $this->sessionResponse();
        $response->headers->setCookie(Cookie::create('remember', 'x', 0, '/shop', 'example.test', true, true, false, 'strict'));

        $this->handle($request, $response);

        $carriesCookies = [] !== $response->headers->getCookies() || $response->headers->has('Set-Cookie');

        self::assertFalse($response->headers->hasCacheControlDirective('public') && $carriesCookies);
    }

    /**
     * @return iterable<string, array{string, string, string, string, bool}>
     */
    public static function everyCase(): iterable
    {
        foreach (['disabled', 'announce', 'enabled'] as $mode) {
            foreach ([['/', 'index'], ['/horatio.html', 'product'], ['/salon.html', 'category'], ['/login', 'login']] as [$path, $view]) {
                foreach (['GET', 'HEAD', 'POST'] as $method) {
                    foreach ([false, true] as $cookie) {
                        yield \sprintf('%s %s %s %s%s', $mode, $method, $path, $view, $cookie ? ' with cookie' : '') => [$mode, $path, $view, $method, $cookie];
                    }
                }
            }
        }
    }

    private function mode(SharedCacheMode $mode): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, $mode->value);
    }

    private function request(string $path, string $view, string $method = 'GET'): Request
    {
        $request = TheliaRequest::create($path, $method);
        $request->attributes->set('_view', $view);

        return $request;
    }

    private function sessionResponse(): Response
    {
        $response = new Response('<html></html>');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->headers->setCookie(Cookie::create('PHPSESSID', 'abc', 0, '/', null, false, true, false, 'lax'));
        $response->headers->clearCookie('thelia_cart');

        return $response;
    }

    private function handle(
        Request $request,
        Response $response,
        ?PricingActivityChecker $checker = null,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        ?SharedCacheKeyCollector $collector = null,
        string $driver = '',
    ): void {
        if (null === $checker) {
            $checker = $this->createStub(PricingActivityChecker::class);
            $checker->method('hasVisitorDependentPricing')->willReturn(false);
        }

        $collector ??= new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);

        (new SharedCacheListener(new SharedCachePolicy($checker, $collector), $collector, $driver))
            ->onKernelResponse(new ResponseEvent(self::$kernel, $request, $requestType, $response));
    }

    /**
     * @return array{string, array<string, list<string|null>>}
     */
    private function snapshot(Response $response): array
    {
        $headers = $response->headers->all();
        unset($headers['surrogate-key'], $headers['xkey']);
        ksort($headers);

        return [(string) $response->getStatusCode(), $headers];
    }
}

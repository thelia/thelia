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

use Symfony\Component\HttpFoundation\Cookie;
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

/**
 * A page that depends on the country of the visitor is only shared when the country
 * comes from a header the cache can vary on.
 */
final class VisitorCountrySharedCacheTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testInHeaderModeTheConfiguredHeaderIsAddedToTheVary(): void
    {
        $this->configure('suggest', 'header');
        ConfigQuery::write('visitor_country_header', 'X-Geo');
        ConfigQuery::resetCache();

        $response = $this->handle();

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertContains('X-Geo', $response->getVary());
        self::assertContains('X-Forwarded-Proto', $response->getVary());
    }

    public function testTheDefaultHeaderIsAddedToTheVaryInApplyMode(): void
    {
        $this->configure('apply', 'header');

        self::assertContains('CF-IPCountry', $this->handle()->getVary());
    }

    public function testInIpDatabaseModeThePageIsNotShared(): void
    {
        $this->configure('apply', 'ip_database');

        $response = $this->handle();

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertNotContains('CF-IPCountry', $response->getVary());
    }

    public function testInOffModeNothingChanges(): void
    {
        foreach (['header', 'ip_database'] as $source) {
            $this->configure('off', $source);

            $response = $this->handle();

            self::assertTrue($response->headers->hasCacheControlDirective('public'), $source);
            self::assertEqualsCanonicalizing(['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Host'], $response->getVary(), $source);
        }
    }

    public function testThePolicyAnswersTheSameWayOnItsOwn(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);
        $checker = $this->createStub(PricingActivityChecker::class);
        $checker->method('hasVisitorDependentPricing')->willReturn(false);
        $policy = new SharedCachePolicy($checker, $collector);
        $request = TheliaRequest::create('/horatio.html');
        $request->attributes->set('_view', 'product');

        $this->configure('suggest', 'ip_database');
        self::assertFalse($policy->isShareable($request, new Response('x')));

        $this->configure('suggest', 'header');
        self::assertTrue($policy->isShareable($request, new Response('x')));
    }

    private function configure(string $mode, string $source): void
    {
        ConfigQuery::write(SharedCacheMode::CONFIG_KEY, SharedCacheMode::ENABLED->value);
        ConfigQuery::write('visitor_country_mode', $mode);
        ConfigQuery::write('visitor_country_source', $source);
        ConfigQuery::resetCache();
    }

    private function handle(): Response
    {
        $request = TheliaRequest::create('/horatio.html');
        $request->attributes->set('_view', 'product');
        $response = new Response('<html></html>');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->headers->setCookie(Cookie::create('PHPSESSID', 'abc', 0, '/', null, false, true, false, 'lax'));

        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);
        $checker = $this->createStub(PricingActivityChecker::class);
        $checker->method('hasVisitorDependentPricing')->willReturn(false);

        (new SharedCacheListener(new SharedCachePolicy($checker, $collector), $collector))
            ->onKernelResponse(new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}

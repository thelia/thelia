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

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySession;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Lang;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The shop offers the visitor the country it detected, and the visitor answers: accept it
 * and the prices follow, or turn it down. The answer is a POST with the stateless token of
 * the page, whatever the visitor sends along is checked, and the checkout never shows the
 * offer.
 *
 * The banner belongs to the theme, which ships on its own release cycle: a theme older than
 * this feature is reported as skipped rather than failed.
 */
final class CountrySuggestionBannerTest extends WebIntegrationTestCase
{
    private const ACCEPT_ROUTE = 'country_suggestion_accept';
    private const DECLINE_ROUTE = 'country_suggestion_decline';
    private const BANNER = '.CountrySuggestion';

    private ?Country $belgium = null;

    private ?int $belgiumVisible = null;

    /** @var array<string, string> */
    private array $settings = [];

    /**
     * What the session held when the last response left: the session of a request is gone
     * by the time the test looks, and the one of the container is not the visitor's.
     *
     * @var array<string, mixed>
     */
    private array $lastSession = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (
            !class_exists(\FlexyBundle\Components\Molecules\CountrySuggestion\Base::class)
            || null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::ACCEPT_ROUTE)
        ) {
            self::markTestSkipped('The installed front-office theme does not offer the detected country yet.');
        }

        $this->getService(EventDispatcherInterface::class)->addListener(
            KernelEvents::RESPONSE,
            function (ResponseEvent $event): void {
                if ($event->isMainRequest() && $event->getRequest()->hasSession(true)) {
                    $this->lastSession = $event->getRequest()->getSession()->all();
                }
            },
            -1000,
        );

        $this->belgium = CountryQuery::create()->findOneByIsoalpha2('BE');

        if (null === $this->belgium) {
            self::markTestSkipped('The test database has no Belgium.');
        }

        foreach (['visitor_country_mode', 'visitor_country_source'] as $name) {
            $this->settings[$name] = (string) ConfigQuery::read($name, '');
        }

        $this->belgiumVisible = $this->belgium->getVisible();
        $this->belgium->setVisible(1)->save($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (null !== $this->belgium) {
            $this->belgium->setVisible($this->belgiumVisible ?? 1)->save($this->getPropelConnection());
        }

        // The shop settings are cached outside the database: the rollback would leave this
        // test's mode there, and the next test would open its first request on it.
        foreach ($this->settings as $name => $value) {
            ConfigQuery::write($name, $value);
        }

        ConfigQuery::resetCache();

        parent::tearDown();

        ConfigQuery::resetCache();
    }

    public function testInSuggestModeABelgianVisitorIsOfferedBelgium(): void
    {
        $crawler = $this->visitorFrom('suggest');

        self::assertCount(1, $crawler->filter(self::BANNER));
        self::assertStringContainsString($this->belgiumName(), $crawler->filter(self::BANNER)->text());
        self::assertCount(1, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::ACCEPT_ROUTE).'"] input[name="country_id"][value="'.$this->belgium?->getId().'"]'));
        self::assertCount(1, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::DECLINE_ROUTE).'"]'));
    }

    public function testInApplyModeThereIsNothingToOffer(): void
    {
        self::assertCount(0, $this->visitorFrom('apply')->filter(self::BANNER));
    }

    public function testInOffModeThereIsNothingToOffer(): void
    {
        self::assertCount(0, $this->visitorFrom('off')->filter(self::BANNER));
    }

    public function testAnUnlistedCountryIsNotOffered(): void
    {
        $this->belgium?->setVisible(0)->save($this->getPropelConnection());

        self::assertCount(0, $this->visitorFrom('suggest')->filter(self::BANNER));
    }

    public function testAcceptingTheCountryRemembersItAndClosesTheBanner(): void
    {
        $crawler = $this->visitorFrom('suggest');

        $this->client->request('POST', $this->url(self::ACCEPT_ROUTE), [
            'country_id' => (string) $this->belgium?->getId(),
            '_token' => $this->tokenOf($crawler),
            'redirect' => '/contact',
        ]);

        self::assertResponseRedirects('/contact', 303);
        self::assertSame($this->belgium?->getId(), $this->sessionValue(VisitorCountrySession::ID_KEY));

        $next = $this->client->request('GET', '/contact', server: ['HTTP_CF_IPCOUNTRY' => 'BE']);

        self::assertCount(0, $next->filter(self::BANNER));
        self::assertSame($this->belgium?->getId(), $this->sessionValue(VisitorCountrySession::ID_KEY));
    }

    public function testDecliningClosesTheBannerAndRemembersNothing(): void
    {
        $crawler = $this->visitorFrom('suggest');

        $this->client->request('POST', $this->url(self::DECLINE_ROUTE), ['_token' => $this->tokenOf($crawler), 'redirect' => '/contact']);

        self::assertResponseRedirects('/contact', 303);
        self::assertNull($this->sessionValue(VisitorCountrySession::ID_KEY));

        $next = $this->client->request('GET', '/contact', server: ['HTTP_CF_IPCOUNTRY' => 'BE']);

        self::assertCount(0, $next->filter(self::BANNER));
    }

    public function testAForgedTokenIsRefusedAndNothingIsRemembered(): void
    {
        $this->visitorFrom('suggest');

        foreach ([self::ACCEPT_ROUTE, self::DECLINE_ROUTE] as $route) {
            $this->client->request('POST', $this->url($route), ['country_id' => (string) $this->belgium?->getId(), '_token' => 'forged']);
            self::assertResponseStatusCodeSame(403, $route);

            $this->client->request('POST', $this->url($route), ['country_id' => (string) $this->belgium?->getId()]);
            self::assertResponseStatusCodeSame(403, $route.' without a token');
        }

        self::assertNull($this->sessionValue(VisitorCountrySession::ID_KEY));
        self::assertNotTrue($this->sessionValue(VisitorCountrySession::ANSWERED_KEY));
        self::assertCount(1, $this->client->request('GET', '/', server: ['HTTP_CF_IPCOUNTRY' => 'BE'])->filter(self::BANNER));
    }

    public function testACountryTheShopDoesNotShowIsNotRemembered(): void
    {
        $crawler = $this->visitorFrom('suggest');
        $this->belgium?->setVisible(0)->save($this->getPropelConnection());

        $this->client->request('POST', $this->url(self::ACCEPT_ROUTE), ['country_id' => (string) $this->belgium?->getId(), '_token' => $this->tokenOf($crawler)]);

        self::assertResponseRedirects();
        self::assertNull($this->sessionValue(VisitorCountrySession::ID_KEY));
    }

    public function testAnExternalRedirectionIsReplacedByTheHomePage(): void
    {
        $crawler = $this->visitorFrom('suggest');

        $this->client->request('POST', $this->url(self::DECLINE_ROUTE), ['_token' => $this->tokenOf($crawler), 'redirect' => 'https://evil.example.net/x']);

        self::assertResponseRedirects('/', 303);
    }

    public function testTheCheckoutNeverShowsTheOffer(): void
    {
        $this->visitorFrom('suggest');

        $crawler = $this->client->request('GET', '/checkout/cart', server: ['HTTP_CF_IPCOUNTRY' => 'BE']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The cart page must render.');
        self::assertCount(0, $crawler->filter(self::BANNER));
        self::assertCount(1, $this->client->request('GET', '/contact', server: ['HTTP_CF_IPCOUNTRY' => 'BE'])->filter(self::BANNER), 'Elsewhere the offer is still there.');
    }

    public function testWithOneDomainPerLanguageAcceptingIsAVisitToTheDomainOfTheLanguageOfTheCountry(): void
    {
        $this->languageOfBelgium('https://be.example.test');
        $this->settings['one_domain_foreach_lang'] ??= (string) ConfigQuery::read('one_domain_foreach_lang', '0');

        $crawler = $this->visitorFrom('suggest', ['one_domain_foreach_lang' => '1']);

        self::assertCount(1, $crawler->filter(self::BANNER));
        self::assertCount(0, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::ACCEPT_ROUTE).'"]'));
        self::assertCount(1, $crawler->filter(self::BANNER.' a[href="/?lang=nl_BE"]'));
        self::assertCount(1, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::DECLINE_ROUTE).'"]'), 'Closing stays a POST.');
    }

    public function testALanguageWithoutADomainDoesNotTurnAcceptingIntoALink(): void
    {
        $this->languageOfBelgium('');
        $this->settings['one_domain_foreach_lang'] ??= (string) ConfigQuery::read('one_domain_foreach_lang', '0');

        $crawler = $this->visitorFrom('suggest', ['one_domain_foreach_lang' => '1']);

        self::assertCount(1, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::ACCEPT_ROUTE).'"]'));
        self::assertCount(0, $crawler->filter(self::BANNER.' a[href*="lang="]'));
    }

    public function testWithoutOneDomainPerLanguageAcceptingStaysAForm(): void
    {
        $this->languageOfBelgium('https://be.example.test');

        $crawler = $this->visitorFrom('suggest');

        self::assertCount(1, $crawler->filter(self::BANNER.' form[action="'.$this->url(self::ACCEPT_ROUTE).'"]'));
        self::assertCount(0, $crawler->filter(self::BANNER.' a[href*="lang="]'));
    }

    private function languageOfBelgium(string $url): void
    {
        (new Lang())
            ->setTitle('Nederlands (Belgie)')
            ->setCode('be')
            ->setLocale('nl_BE')
            ->setUrl($url)
            ->setActive(1)
            ->setVisible(1)
            ->setDateFormat('d/m/Y')
            ->setTimeFormat('H:i:s')
            ->setDatetimeFormat('d/m/Y H:i:s')
            ->setDecimalSeparator(',')
            ->setThousandsSeparator('.')
            ->setDecimals('2')
            ->setPosition(99)
            ->save($this->getPropelConnection());
    }

    /**
     * The first request opens the session: a config written before it loses it.
     */
    /**
     * @param array<string, string> $more other shop settings, written with the mode
     */
    private function visitorFrom(string $mode, array $more = []): Crawler
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        ConfigQuery::write('visitor_country_mode', $mode);
        ConfigQuery::write('visitor_country_source', 'header');

        foreach ($more as $name => $value) {
            ConfigQuery::write($name, $value);
        }

        ConfigQuery::resetCache();

        $crawler = $this->client->request('GET', '/', server: ['HTTP_CF_IPCOUNTRY' => 'BE']);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function tokenOf(Crawler $crawler): string
    {
        return (string) $crawler->filter(self::BANNER.' form[action="'.$this->url(self::DECLINE_ROUTE).'"] input[name="_token"]')->attr('value');
    }

    private function belgiumName(): string
    {
        return (string) $this->belgium?->setLocale('en_US')->getTitle();
    }

    private function sessionValue(string $key): mixed
    {
        return $this->lastSession[$key] ?? null;
    }

    private function url(string $route): string
    {
        return $this->getService(RouterInterface::class)->generate($route);
    }
}

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

namespace Thelia\Tests\Integration\Domain\Localization\VisitorCountry;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Domain\Localization\Service\CountryService;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryDetector;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryListener;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryMode;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySession;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySettings;
use Thelia\Domain\Localization\VisitorCountryFacade;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class VisitorCountryFacadeTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();

        Country::resetDefaultCountryCache();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testTheModeIsOffUntilTheShopChoosesOne(): void
    {
        self::assertSame(VisitorCountryMode::Off, $this->facade()->mode());
    }

    public function testAnUnknownModeIsOff(): void
    {
        $this->mode('everything');

        self::assertSame(VisitorCountryMode::Off, $this->facade()->mode());
    }

    public function testTheListenerDetectsOnceAndAppliesTheCountryInApplyMode(): void
    {
        $this->mode('apply');
        $belgium = $this->belgium();

        $this->visit('BE');

        $session = $this->session();
        self::assertSame($belgium->getId(), $session->get('thelia.visitor_country.detected'));
        self::assertSame($belgium->getId(), $session->get('thelia.visitor_country.id'));
        self::assertSame($belgium->getId(), $this->facade()->currentCountry()->getId());
        self::assertSame($belgium->getId(), $this->facade()->detectedCountry()?->getId());
    }

    public function testInSuggestModeTheCountryIsDetectedButNotApplied(): void
    {
        $this->mode('suggest');
        $belgium = $this->belgium();

        $this->visit('BE');

        $session = $this->session();
        self::assertSame($belgium->getId(), $session->get('thelia.visitor_country.detected'));
        self::assertFalse($session->has('thelia.visitor_country.id'));
        self::assertSame(Country::getDefaultCountry()->getId(), $this->facade()->currentCountry()->getId());
    }

    public function testAVisitorWhoseCountryCannotBeFoundIsRememberedAsSuch(): void
    {
        $this->mode('apply');

        $this->visit('XX');

        self::assertSame(0, $this->session()->get('thelia.visitor_country.detected'));
        self::assertFalse($this->session()->has('thelia.visitor_country.id'));
        self::assertNull($this->facade()->detectedCountry());
    }

    public function testTheDetectionDoesNotRunAgainOnceAttempted(): void
    {
        $this->mode('apply');
        $this->belgium();
        $this->visit('BE');
        $this->session()->set('thelia.visitor_country.id', $this->visitedId('FR'));

        $this->visit('NL');

        self::assertSame($this->visitedId('BE'), $this->session()->get('thelia.visitor_country.detected'));
        self::assertSame($this->visitedId('FR'), $this->session()->get('thelia.visitor_country.id'));
    }

    public function testNothingIsDetectedInOffMode(): void
    {
        $this->visit('BE');

        self::assertFalse($this->session()->has('thelia.visitor_country.detected'));
        self::assertNull($this->facade()->detectedCountry());
    }

    #[DataProvider('backendPaths')]
    public function testTheBackOfficeAndTheApiAreNotTouched(string $path): void
    {
        $this->mode('apply');

        $this->visit('BE', $path);

        self::assertFalse($this->session()->has('thelia.visitor_country.detected'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function backendPaths(): iterable
    {
        yield 'admin' => ['/admin/home'];
        yield 'admin root' => ['/admin'];
        yield 'encoded admin' => ['/%61dmin/home'];
        yield 'api' => ['/api/front/products'];
    }

    public function testASubRequestIsNotTouched(): void
    {
        $this->mode('apply');
        $this->session()->start();
        $request = $this->requestWithHeader('BE');

        $this->getService(VisitorCountryListener::class)
            ->onKernelRequest(new RequestEvent(self::$kernel, $request, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($this->session()->has('thelia.visitor_country.detected'));
    }

    public function testAnonymousApplyTaxesInTheDetectedCountry(): void
    {
        $this->mode('apply');
        $belgium = $this->belgium();
        $this->visit('BE');

        self::assertNotSame($belgium->getId(), Country::getDefaultCountry()->getId());
        self::assertSame($belgium->getId(), $this->getService(TaxEngine::class)->getDeliveryCountry()->getId());
    }

    public function testTheDeliveryAddressOfTheCartComesBeforeTheDetectedCountry(): void
    {
        $this->mode('apply');
        $this->belgium();
        $this->visit('BE');

        $factory = $this->createFixtureFactory();
        $spain = $factory->country(['isocode' => '902', 'isoalpha2' => 'YA', 'isoalpha3' => 'YAA']);
        $cart = $factory->cart();
        $cart->setAddressDeliveryId($factory->cartAddress(null, $spain)->getId())->save($this->getPropelConnection());
        $this->session()->setSessionCart($cart);

        self::assertSame($spain->getId(), $this->getService(TaxEngine::class)->getDeliveryCountry()->getId());
    }

    public function testACustomerKeepsTheCascadeOfItsAddresses(): void
    {
        $this->mode('apply');
        $this->belgium();
        $this->visit('BE');

        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title);
        $home = $factory->country(['isocode' => '903', 'isoalpha2' => 'YB', 'isoalpha3' => 'YBB']);
        $factory->address($customer, $home, $title)->setIsDefault(1)->save($this->getPropelConnection());
        $this->session()->setCustomerUser($customer);

        self::assertSame($home->getId(), $this->getService(TaxEngine::class)->getDeliveryCountry()->getId());
    }

    public function testOffModeTaxesInTheDefaultCountryEvenWithACountryInTheSession(): void
    {
        $this->session()->set('thelia.visitor_country.id', $this->belgium()->getId());
        $this->session()->set('thelia.visitor_country.detected', $this->belgium()->getId());

        self::assertSame(VisitorCountryMode::Off, $this->facade()->mode());
        self::assertSame(Country::getDefaultCountry()->getId(), $this->getService(TaxEngine::class)->getDeliveryCountry()->getId());
        self::assertSame(Country::getDefaultCountry()->getId(), $this->facade()->currentCountry()->getId());
        self::assertNull($this->facade()->detectedCountry());
        self::assertSame(Country::getDefaultCountry()->getId(), $this->getService(CountryService::class)->getPreselectedCountry()->getId());
    }

    public function testWithoutARequestTheTaxEngineKeepsTheDefaultCountry(): void
    {
        $this->mode('apply');
        $this->belgium();

        $taxEngine = new TaxEngine(new RequestStack(), static::getContainer()->get('event_dispatcher'), $this->facade());

        self::assertSame(Country::getDefaultCountry()->getId(), $taxEngine->getDeliveryCountry()->getId());
        self::assertSame(Country::getDefaultCountry()->getId(), $this->facadeWithoutRequest()->currentCountry()->getId());
        self::assertNull($this->facadeWithoutRequest()->detectedCountry());
    }

    public function testTheSuggestionIsTheDetectedCountryForAnAnonymousVisitorWhoDidNotAnswer(): void
    {
        $this->mode('suggest');
        $belgium = $this->belgium();
        $this->visit('BE');

        self::assertSame($belgium->getId(), $this->facade()->suggestion()?->getId());
    }

    public function testThereIsNoSuggestionOutsideSuggestMode(): void
    {
        $this->mode('apply');
        $this->belgium();
        $this->visit('BE');

        self::assertNull($this->facade()->suggestion());
    }

    public function testThereIsNoSuggestionForTheCountryTheVisitorAlreadyShopsIn(): void
    {
        $this->mode('suggest');
        $this->visit('FR');
        $this->session()->set('thelia.visitor_country.id', $this->visitedId('FR'));

        self::assertNull($this->facade()->suggestion());
    }

    public function testThereIsNoSuggestionForACustomer(): void
    {
        $this->mode('suggest');
        $this->belgium();
        $this->visit('BE');
        $factory = $this->createFixtureFactory();
        $this->session()->setCustomerUser($factory->customer($factory->customerTitle()));

        self::assertNull($this->facade()->suggestion());
    }

    public function testAcceptingRetainsTheCountryAndEndsTheSuggestion(): void
    {
        $this->mode('suggest');
        $belgium = $this->belgium();
        $this->visit('BE');

        self::assertTrue($this->facade()->accept($belgium->getId()));

        self::assertSame($belgium->getId(), $this->session()->get('thelia.visitor_country.id'));
        self::assertTrue($this->session()->get('thelia.visitor_country.answered'));
        self::assertSame($belgium->getId(), $this->facade()->currentCountry()->getId());
        self::assertSame($belgium->getId(), $this->getService(TaxEngine::class)->getDeliveryCountry()->getId());
        self::assertNull($this->facade()->suggestion());
    }

    public function testAcceptingACountryThatIsNotVisibleIsRefusedAndWritesNothing(): void
    {
        $this->mode('suggest');
        $hidden = $this->createFixtureFactory()->country(['isocode' => '904', 'isoalpha2' => 'YC', 'isoalpha3' => 'YCC', 'visible' => 0]);

        self::assertFalse($this->facade()->accept($hidden->getId()));
        self::assertFalse($this->facade()->accept(0));
        self::assertFalse($this->facade()->accept(999999999));

        self::assertFalse($this->session()->has('thelia.visitor_country.id'));
        self::assertFalse($this->session()->has('thelia.visitor_country.answered'));
    }

    public function testDecliningEndsTheSuggestionAndKeepsTheDefaultCountry(): void
    {
        $this->mode('suggest');
        $this->belgium();
        $this->visit('BE');
        self::assertNotNull($this->facade()->suggestion());

        $this->facade()->decline();

        self::assertTrue($this->session()->get('thelia.visitor_country.answered'));
        self::assertNull($this->facade()->suggestion());
        self::assertSame(Country::getDefaultCountry()->getId(), $this->facade()->currentCountry()->getId());
    }

    public function testThePreselectedCountryIsTheOneTheVisitorShopsIn(): void
    {
        $this->mode('apply');
        $belgium = $this->belgium();
        $this->visit('BE');
        $service = $this->getService(CountryService::class);

        self::assertSame($belgium->getId(), $service->getPreselectedCountry()->getId());
        self::assertNotSame($belgium->getId(), $service->getDefaultCountry()->getId(), 'getDefaultCountry() stays the default of the shop');
    }

    public function testResolveFromRequestReadsTheRequestAndStoresNothing(): void
    {
        $this->mode('suggest');
        $belgium = $this->belgium();

        $country = $this->facade()->resolveFromRequest($this->requestWithHeader('BE'));

        self::assertSame($belgium->getId(), $country?->getId());
        self::assertFalse($this->session()->has('thelia.visitor_country.detected'));
    }

    public function testResolveFromRequestIsNullInOffMode(): void
    {
        $this->belgium();

        self::assertNull($this->facade()->resolveFromRequest($this->requestWithHeader('BE')));
    }

    /**
     * The tax engine asks for the country once per price on a page: in production, where
     * Propel keeps an instance pool, the retained country is read from the database once.
     */
    public function testTheRetainedCountryIsReadOnceWhenTheInstancePoolIsOn(): void
    {
        $this->mode('apply');
        $this->visit('BE');
        $facade = $this->facade();

        Propel::enableInstancePooling();

        try {
            $facade->currentCountry();

            $statements = $this->recordSqlQueries(static function () use ($facade): void {
                for ($price = 0; $price < 20; ++$price) {
                    $facade->currentCountry();
                }
            });
        } finally {
            Propel::disableInstancePooling();
        }

        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'country'));
    }

    private function belgium(): Country
    {
        $belgium = CountryQuery::create()->filterByVisible(1)->findOneByIsoalpha2('BE');
        self::assertNotNull($belgium, 'the test database seeds Belgium');

        return $belgium;
    }

    private function visitedId(string $isoAlpha2): int
    {
        return (int) CountryQuery::create()->findOneByIsoalpha2($isoAlpha2)?->getId();
    }

    private function mode(string $mode): void
    {
        ConfigQuery::write('visitor_country_mode', $mode);
        ConfigQuery::resetCache();
    }

    private function facade(): VisitorCountryFacade
    {
        return $this->getService(VisitorCountryFacade::class);
    }

    private function facadeWithoutRequest(): VisitorCountryFacade
    {
        return new VisitorCountryFacade(
            new VisitorCountrySettings(),
            $this->getService(VisitorCountryDetector::class),
            new VisitorCountrySession(new RequestStack()),
            new RequestStack(),
        );
    }

    private function session(): SessionInterface
    {
        return $this->getService(RequestStack::class)->getMainRequest()->getSession();
    }

    private function requestWithHeader(string $country, string $path = '/'): Request
    {
        $request = TheliaRequest::create($path);
        $request->headers->set('CF-IPCountry', $country);
        $request->setSession($this->session());

        return $request;
    }

    private function visit(string $country, string $path = '/'): void
    {
        $session = $this->session();
        if (!$session->isStarted()) {
            $session->start();
        }

        $this->getService(VisitorCountryListener::class)->onKernelRequest(
            new RequestEvent(self::$kernel, $this->requestWithHeader($country, $path), HttpKernelInterface::MAIN_REQUEST),
        );
    }
}

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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Controller\Configuration\CheckoutStepController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CheckoutStepTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the configurable checkout US: the "Checkout steps" screen
 * (BackOfficeDefaultTwigBundle\Controller\Configuration\CheckoutStepController),
 * which is where a merchant turns a step off, reorders the tunnel and picks the
 * layout the theme renders it with.
 *
 * Every test restores the shipped configuration (four active steps, in order, in
 * the "steps" layout) in tearDown: the rest of the suites order through that
 * tunnel and would fail on a checkout left one step short.
 */
final class CheckoutStepConfigurationTest extends WebIntegrationTestCase
{
    private const SCREEN_URL = '/admin/configuration/checkout-step';

    /**
     * The shipped configuration, restored after every test.
     *
     * @var array<string, int>
     */
    private const SHIPPED_POSITIONS = [
        CheckoutStep::CODE_CART => 1,
        CheckoutStep::CODE_DELIVERY => 2,
        CheckoutStep::CODE_PAYMENT => 3,
        CheckoutStep::CODE_CONFIRMATION => 4,
    ];

    private AdminSessionInjector $injector;

    private int $synchronisations = 0;

    private ?\Closure $synchronisationCounter = null;

    protected function setUp(): void
    {
        if (!class_exists(CheckoutStepController::class)) {
            self::markTestSkipped('The installed back-office theme predates the configurable checkout.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // setUp() may have skipped before wiring the injector, and before the
        // Propel connection this restores the steps through is usable.
        if (isset($this->injector)) {
            $this->injector->clear();
            $this->restoreShippedConfiguration();
        }

        if (null !== $this->synchronisationCounter) {
            static::getContainer()->get('event_dispatcher')->removeListener(
                TheliaEvents::CHECKOUT_STEP_SYNCHRONIZE,
                $this->synchronisationCounter,
            );
            $this->synchronisationCounter = null;
        }

        parent::tearDown();
    }

    /**
     * Counts the synchronisations the screen asks for, from the moment this is called.
     *
     * Read on the event rather than on the rows: the point is that a GET in a shop with
     * nothing to create does not reach the writing side at all, and a shop where there
     * happens to be nothing to write would pass an assertion made on the table alone.
     */
    private function countTheSynchronisations(): void
    {
        $this->synchronisations = 0;
        $this->synchronisationCounter = function (): void {
            ++$this->synchronisations;
        };

        static::getContainer()->get('event_dispatcher')->addListener(
            TheliaEvents::CHECKOUT_STEP_SYNCHRONIZE,
            $this->synchronisationCounter,
            1024,
        );
    }

    private function loginAdmin(): void
    {
        // FixtureFactory built directly (not via createFixtureFactory()): that
        // helper pushes a synthetic Request onto the stack, which would then
        // become the "main" request the security context resolves the session
        // from for every subsequent client->request() call in this test (same
        // rationale as ConsentConfigurationTest::loginAdmin()).
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testListShowsTheFourShippedStepsInTunnelOrder(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);
        $content = (string) $this->client->getResponse()->getContent();

        $codes = $this->renderedCodes();
        self::assertSame(
            [
                CheckoutStep::CODE_CART,
                CheckoutStep::CODE_DELIVERY,
                CheckoutStep::CODE_PAYMENT,
                CheckoutStep::CODE_CONFIRMATION,
            ],
            $codes,
            'The screen lists the steps in the order the buyer walks through them.',
        );

        // The wording comes from checkout_step_i18n, not from the code.
        self::assertStringContainsString('Delivery', $content);
    }

    /**
     * Opening the list is a read.
     *
     * The screen creates the row of a step that has none, which is what gives a module's
     * step a line here without a migration of its own — but it used to do so on every
     * GET, refresh and back button included, in a shop where there was nothing at all to
     * create. A page a merchant only looked at wrote to the database.
     */
    public function testOpeningTheListWritesNothingWhenEveryDeclaredStepHasItsRow(): void
    {
        $this->loginAdmin();
        $this->countTheSynchronisations();

        $this->assertPageRenders(self::SCREEN_URL);

        self::assertSame(
            0,
            $this->synchronisations,
            'Every step the installed code declares has a row: opening the list must write nothing.',
        );
    }

    /**
     * The other half of the same rule: a step whose row is missing still gets one.
     *
     * Stands in for a module installed after the shop was: its step declares itself in
     * the code, and the screen is where the row appears.
     */
    public function testOpeningTheListCreatesTheRowOfAStepThatHasNone(): void
    {
        $this->loginAdmin();
        $this->countTheSynchronisations();

        $this->step(CheckoutStep::CODE_DELIVERY)->delete($this->getPropelConnection());
        CheckoutStepTableMap::clearInstancePool();
        CheckoutStepTableMap::clearRelatedInstancePool();

        $this->assertPageRenders(self::SCREEN_URL);

        self::assertSame(
            1,
            $this->synchronisations,
            'A step declared by the code with no row of its own must be created when the screen is opened.',
        );
        self::assertNotNull(
            CheckoutStepQuery::create()->findOneByCode(CheckoutStep::CODE_DELIVERY),
            'The row the synchronisation was asked for must actually be there.',
        );
    }

    public function testAMandatoryStepIsShownWithoutAnActivationToggle(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);
        $crawler = $this->client->getCrawler();

        foreach (CheckoutStep::REQUIRED_CODES as $code) {
            self::assertCount(
                0,
                $crawler->filter(\sprintf('tr[data-row-id="%s"] a[data-testid="datatable-active-toggle"]', $code)),
                \sprintf('The mandatory "%s" step must not offer a switch the server would refuse.', $code),
            );
        }

        self::assertCount(
            1,
            $crawler->filter(\sprintf(
                'tr[data-row-id="%s"] a[data-testid="datatable-active-toggle"]',
                CheckoutStep::CODE_DELIVERY,
            )),
            'The optional delivery step must expose a working activation switch.',
        );
    }

    public function testTheDeliveryStepCanBeTurnedOff(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);
        $toggleUrl = $this->client->getCrawler()
            ->filter(\sprintf(
                'tr[data-row-id="%s"] a[data-testid="datatable-active-toggle"]',
                CheckoutStep::CODE_DELIVERY,
            ))
            ->attr('href');
        self::assertIsString($toggleUrl);

        // The link says which state it asks for, read off the row it was drawn on. A
        // link that only said "flip it" would turn the step back on when the merchant
        // opened it twice, or when a second administrator had already clicked it.
        self::assertStringContainsString(
            'active=0',
            $toggleUrl,
            'The switch of an active step must ask for it to be turned off, not for it to be flipped.',
        );

        $this->client->request('GET', $toggleUrl);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Turning a step off redirects back to the list.',
        );
        self::assertFalse(
            $this->stepAsStored(CheckoutStep::CODE_DELIVERY)->isActive(),
            'A shop that ships nothing must be able to drop the delivery screen.',
        );
    }

    /**
     * The switch is a link, so nothing but the token stands between a forged request and
     * a shop whose checkout lost a step.
     */
    public function testTurningAStepOffWithoutTheCsrfTokenWritesNothing(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);

        $this->client->request('GET', self::SCREEN_URL.'/toggle-active?'.http_build_query([
            'checkout_step_code' => CheckoutStep::CODE_DELIVERY,
            'active' => 0,
        ]));

        self::assertTrue(
            $this->stepAsStored(CheckoutStep::CODE_DELIVERY)->isActive(),
            'A request carrying no CSRF token must leave the configuration exactly as it was.',
        );
    }

    public function testTheServerRefusesToTurnOffTheMandatoryPaymentStep(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);

        // The payment row carries no switch, so the URL is forged from the token
        // the page did hand out: the refusal has to come from the server, not
        // only from a link the template chose not to render.
        $forged = $this->tokenizedUrl('/toggle-active', [
            'checkout_step_code' => CheckoutStep::CODE_PAYMENT,
            'active' => 0,
        ]);

        $this->client->request('GET', $forged);

        self::assertTrue(
            $this->stepAsStored(CheckoutStep::CODE_PAYMENT)->isActive(),
            'Nobody can buy through a checkout with no payment screen: the step stays on.',
        );
    }

    public function testAStepCanBeMovedWithinTheTunnel(): void
    {
        $this->loginAdmin();

        // A tunnel of exactly the four shipped steps has only one sellable order,
        // so there is nothing to reorder until a module adds a step of its own.
        $this->seedExtraStep();

        $this->assertPageRenders(self::SCREEN_URL);

        $this->client->request('POST', $this->tokenizedUrl('/update-position', [
            'checkout_step_code' => 'test_gift_message',
            'position' => 2,
        ]));

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            [
                CheckoutStep::CODE_CART => 1,
                'test_gift_message' => 2,
                CheckoutStep::CODE_DELIVERY => 3,
                CheckoutStep::CODE_PAYMENT => 4,
                CheckoutStep::CODE_CONFIRMATION => 5,
            ],
            $this->storedPositions(),
            'The moved step takes the place asked for and the list is renumbered around it.',
        );
    }

    public function testTheServerRefusesAPositionThatBreaksTheTunnel(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);

        $this->client->request('POST', $this->tokenizedUrl('/update-position', [
            'checkout_step_code' => CheckoutStep::CODE_CART,
            'position' => 2,
        ]));

        self::assertSame(
            self::SHIPPED_POSITIONS,
            $this->storedPositions(),
            'The checkout opens on the cart: moving it down is refused and nothing is renumbered.',
        );
    }

    public function testTheDisplayModeCanBeSetToOnePage(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);
        $crawler = $this->client->getCrawler();

        $button = $crawler->filter('[data-testid="checkout-display-mode-save-stay"]');
        self::assertGreaterThan(0, $button->count(), 'The screen must expose its display-mode Save button.');

        $form = $button->form([
            'thelia_checkout_display_mode[checkout_display_mode]' => CheckoutDisplayMode::OnePage->value,
        ]);
        $this->client->submit($form);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Saving a known layout must redirect (any 200 here means the form was rejected).',
        );
        self::assertSame(
            CheckoutDisplayMode::OnePage->value,
            ConfigQuery::read('checkout_display_mode'),
        );
    }

    public function testAnUnknownDisplayModeIsRefused(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders(self::SCREEN_URL);
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('[data-testid="checkout-display-mode-save-stay"]')->form();
        $values = $form->getPhpValues();
        // Set past the rendered options: a browser cannot send this, a script can.
        $values['thelia_checkout_display_mode']['checkout_display_mode'] = 'carousel';

        $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(
            400,
            $this->client->getResponse()->getStatusCode(),
            'A layout no theme knows how to render must be rejected, not stored.',
        );
        self::assertSame(
            CheckoutDisplayMode::Steps->value,
            ConfigQuery::read('checkout_display_mode'),
            'The refused value must not reach the configuration table.',
        );
    }

    /**
     * The step codes as the table renders them, in rendered order.
     *
     * @return list<string>
     */
    private function renderedCodes(): array
    {
        return $this->client->getCrawler()
            ->filter('tbody[data-controller="bo-sortable"] tr[data-row-id]')
            ->each(static fn ($row): string => (string) $row->attr('data-row-id'));
    }

    /**
     * @return array<string, int>
     */
    private function storedPositions(): array
    {
        $positions = [];

        foreach (CheckoutStepQuery::create()->orderByPosition()->orderByCode()->find() as $step) {
            $positions[(string) $step->getCode()] = (int) $step->getPosition();
        }

        return $positions;
    }

    private function step(string $code): CheckoutStep
    {
        $step = CheckoutStepQuery::create()->findOneByCode($code);
        self::assertNotNull($step, \sprintf('The installer must have seeded the "%s" step.', $code));

        return $step;
    }

    /**
     * The row as the table holds it, and not as this process happens to remember it.
     *
     * Propel hands back the instance it hydrated earlier in the same process, so a test
     * that asserts nothing was written would be answered by the row it read before the
     * request — and would pass whether the request wrote or not.
     */
    private function stepAsStored(string $code): CheckoutStep
    {
        CheckoutStepTableMap::clearInstancePool();
        CheckoutStepTableMap::clearRelatedInstancePool();

        return $this->step($code);
    }

    /**
     * An URL of the screen carrying the CSRF token the rendered page handed out.
     * TokenProvider answers with one token per session, so the token the sortable
     * table was given is the one every other tokenized action of the page accepts.
     *
     * @param array<string, scalar> $parameters
     */
    private function tokenizedUrl(string $path, array $parameters): string
    {
        $token = $this->client->getCrawler()
            ->filter('tbody[data-controller="bo-sortable"]')
            ->attr('data-bo-sortable-token-value');
        self::assertIsString($token, 'The sortable table must carry the CSRF token its fetch posts back.');

        return self::SCREEN_URL.$path.'?'.http_build_query($parameters + ['_token' => $token]);
    }

    /**
     * A non-mandatory step in the middle of the tunnel, standing in for the one a
     * module (a gift message, a click-and-collect slot) would declare.
     */
    private function seedExtraStep(): void
    {
        (new CheckoutStep())
            ->setCode('test_gift_message')
            ->setPosition(3)
            ->setActive(1)
            ->setMandatory(0)
            ->save($this->getPropelConnection());

        $this->step(CheckoutStep::CODE_PAYMENT)->setPosition(4)->save($this->getPropelConnection());
        $this->step(CheckoutStep::CODE_CONFIRMATION)->setPosition(5)->save($this->getPropelConnection());
    }

    private function restoreShippedConfiguration(): void
    {
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::Steps->value, false);

        $connection = $this->getPropelConnection();

        foreach (CheckoutStepQuery::create()->find($connection) as $step) {
            $code = (string) $step->getCode();

            if (!isset(self::SHIPPED_POSITIONS[$code])) {
                $step->delete($connection);
                continue;
            }

            $step->setPosition(self::SHIPPED_POSITIONS[$code])->setActive(1)->save($connection);
        }
    }
}

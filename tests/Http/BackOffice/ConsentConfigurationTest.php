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

use BackOfficeDefaultTwigBundle\Controller\Configuration\ConsentController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\OrderConsent;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the checkout consents US: the "Checkout consents" CRUD
 * screen (BackOfficeDefaultTwigBundle\Controller\Configuration\ConsentController)
 * and the read-only consents block on the order detail page
 * (BackOfficeDefaultTwigBundle\Service\Order\OrderDetailContextBuilder).
 */
final class ConsentConfigurationTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the checkout consents has none
        // of the screens this asserts on.
        if (!class_exists(ConsentController::class)) {
            self::markTestSkipped('The installed back-office theme predates the checkout consents.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // setUp() may have skipped before wiring the injector.
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        // FixtureFactory built directly (not via createFixtureFactory()): that
        // helper pushes a synthetic Request onto the stack, which would then
        // become the "main" request the security context resolves the session
        // from for every subsequent client->request() call in this test (same
        // rationale as CustomerGuestCheckoutTest::loginAdmin()).
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testListShowsTheTermsAndConditionsConsent(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/configuration/consent');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString(Consent::CODE_TERMS_AND_CONDITIONS, $content);
    }

    public function testTermsAndConditionsConsentHasNoDeleteButton(): void
    {
        $this->loginAdmin();

        $terms = ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS);
        self::assertNotNull($terms, 'The installer must have seeded the terms and conditions consent.');

        $crawler = $this->client->request('GET', '/admin/configuration/consent');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $deleteButton = $crawler->filter(\sprintf(
            '[data-testid="datatable-action-delete"][data-consent-id="%d"]',
            $terms->getId(),
        ));
        self::assertCount(0, $deleteButton, 'The terms and conditions consent must not expose a delete button.');
    }

    public function testCreatingAnOptionalConsentPersistsIt(): void
    {
        $this->loginAdmin();

        $code = 'newsletter_opt_in_'.uniqid();

        $crawler = $this->client->request('GET', '/admin/configuration/consent');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $button = $crawler->filter('[data-testid="consent-create-submit"]');
        self::assertGreaterThan(0, $button->count(), 'The consent list must expose its create-consent submit button.');

        // The "mandatory" checkbox is left untouched (unchecked in the rendered
        // form): this is the "optional consent" the test title promises.
        $form = $button->form([
            'thelia_consent_creation[code]' => $code,
            'thelia_consent_creation[title]' => 'Receive the newsletter',
            'thelia_consent_creation[description]' => 'You may unsubscribe at any time.',
        ]);

        $this->client->submit($form);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Creating a valid consent must redirect (any 200 here means the form was rejected).',
        );

        $created = ConsentQuery::create()->findOneByCode($code);
        self::assertNotNull($created, 'The consent must be persisted.');
        self::assertFalse($created->isMandatory(), 'An optional consent must not be forced mandatory.');
        self::assertTrue($created->isActive(), 'A freshly created consent defaults to active.');
    }

    /**
     * A shop whose front-office theme cannot display the box has to be able to stop
     * requiring it from the screen where it manages the rest — not through hand-written
     * SQL. Deletion is the only thing the terms and conditions still refuse.
     */
    public function testTheTermsAndConditionsConsentCanBeMadeOptionalFromTheBackOffice(): void
    {
        $this->loginAdmin();

        $terms = ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS);
        self::assertNotNull($terms);

        $crawler = $this->client->request('GET', \sprintf('/admin/configuration/consent/update/%d', $terms->getId()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $submit = $crawler->filter('[data-testid="consent-edit-submit"]');
        self::assertGreaterThan(0, $submit->count());

        $form = $submit->form();
        $form['thelia_consent_modification[mandatory]']->untick();
        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertFalse(
            ConsentQuery::create()->findPk($terms->getId())?->isMandatory(),
            'The merchant must be able to stop requiring the box from the back office.',
        );

        // And back again, the day the theme knows how to show it.
        $crawler = $this->client->request('GET', \sprintf('/admin/configuration/consent/update/%d', $terms->getId()));
        $form = $crawler->filter('[data-testid="consent-edit-submit"]')->form();
        $form['thelia_consent_modification[mandatory]']->tick();
        $this->client->submit($form);

        self::assertTrue(ConsentQuery::create()->findPk($terms->getId())?->isMandatory());
    }

    public function testTheTermsAndConditionsConsentKeepsItsActivationSwitch(): void
    {
        $this->loginAdmin();

        $terms = ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS);
        self::assertNotNull($terms);

        $crawler = $this->client->request('GET', '/admin/configuration/consent');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $switch = $crawler->filter(\sprintf(
            'a[data-testid="datatable-active-toggle"][href*="consent_id=%d"]',
            $terms->getId(),
        ));
        self::assertGreaterThan(0, $switch->count(), 'The terms and conditions consent must expose a working activation switch, not a readonly one.');
    }

    public function testOrderDetailPageShowsAcceptedConsents(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order();

        (new OrderConsent())
            ->setOrderId((int) $order->getId())
            ->setConsentCode(Consent::CODE_TERMS_AND_CONDITIONS)
            ->setTitle('I accept the terms and conditions of sale')
            ->setAccepted(1)
            ->save($this->getPropelConnection());

        $this->client->request('GET', \sprintf('/admin/order/update/%d', $order->getId()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('data-testid="order-consents"', $content);
        self::assertStringContainsString('I accept the terms and conditions of sale', $content);
    }

    public function testOrderDetailPageHidesConsentsBlockWhenOrderHasNone(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order();

        $this->client->request('GET', \sprintf('/admin/order/update/%d', $order->getId()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('data-testid="order-consents"', $content);
    }
}

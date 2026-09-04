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

use BackOfficeDefaultTwigBundle\Service\Customer\CustomerFilters;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\ConfigQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the guest checkout US: the "Guest checkout" setting on
 * the store configuration screen. See BackOfficeDefaultTwigBundle\Form\Configuration\ConfigStoreType
 * and Controller\Configuration\ConfigStoreController.
 */
final class ConfigStoreGuestCheckoutTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the guest checkout has none of
        // the screens this asserts on.
        if (!class_exists(CustomerFilters::class) || !\defined(CustomerFilters::class.'::KEY_GUEST')) {
            self::markTestSkipped('The installed back-office theme predates the guest checkout.');
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
        // FixtureFactory built directly (not via createFixtureFactory()): see
        // CustomerGuestCheckoutTest for why that helper cannot be used here.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testStoreConfigurationPageShowsTheThreeGuestCheckoutModes(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/configuration/store');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('config-store-guest-checkout-mode', $content);
        foreach (GuestCheckoutMode::cases() as $mode) {
            self::assertStringContainsString(
                'value="'.$mode->value.'"',
                $content,
                \sprintf('The "%s" guest checkout mode must be one of the select options.', $mode->value),
            );
        }
    }

    public function testSavingTheStoreConfigurationPersistsTheGuestCheckoutMode(): void
    {
        $this->loginAdmin();

        $previous = ConfigQuery::read('guest_checkout_mode', GuestCheckoutMode::Disabled->value);
        $target = $previous === GuestCheckoutMode::Enabled->value
            ? GuestCheckoutMode::EnabledUnlessProductForbids
            : GuestCheckoutMode::Enabled;

        $crawler = $this->client->request('GET', '/admin/configuration/store');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $button = $crawler->filter('[data-testid="config-store-save-stay"]');
        self::assertGreaterThan(0, $button->count(), 'The store configuration form must expose its Save button.');

        // The test database seeds no store configuration, so the rest of the
        // form's required fields (store_name, store_email, address...) are
        // blank on the rendered page. A real submission has to fill them in
        // too, or the form is rejected on those fields, not on the one this
        // test cares about.
        $form = $button->form([
            'thelia_configuration_store[store_name]' => 'Test Store',
            'thelia_configuration_store[store_email]' => 'store@test.com',
            'thelia_configuration_store[store_notification_emails]' => 'store@test.com',
            'thelia_configuration_store[store_address1]' => '1 Main Street',
            'thelia_configuration_store[store_zipcode]' => '75001',
            'thelia_configuration_store[store_city]' => 'Paris',
            'thelia_configuration_store[guest_checkout_mode]' => $target->value,
        ]);

        $this->client->submit($form);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Saving a valid store configuration must redirect (any 200 here means the form was rejected).',
        );
        self::assertSame($target->value, ConfigQuery::read('guest_checkout_mode'));
    }
}

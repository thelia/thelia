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
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the guest checkout US: a guest customer (customer.is_guest)
 * must be visible and filterable in the customer list, and flagged on its own
 * edit page. See BackOfficeDefaultTwigBundle\Service\Customer\CustomerListRowPresenter,
 * CustomerFilters and customer/edit.html.twig.
 */
final class CustomerGuestCheckoutTest extends WebIntegrationTestCase
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
        // Same rationale as AdminPagesSmokeTest::loginAdmin(): build the factory
        // without createFixtureFactory() so the admin session lands on the
        // request the security context actually reads from.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testCustomerListShowsGuestBadgeAndAppliesGuestFilter(): void
    {
        $this->loginAdmin();

        // FixtureFactory built directly (not via createFixtureFactory()): that
        // helper pushes a synthetic Request onto the stack, which would then
        // become the "main" request the security context resolves the session
        // from for every subsequent client->request() call in this test.
        $factory = new FixtureFactory($this->getPropelConnection());
        $title = $factory->customerTitle();
        $guest = $factory->guestCustomer($title, [
            'firstname' => 'Gustave',
            'lastname' => 'Guestman',
            'email' => 'guest-badge-probe@test.com',
        ]);
        $registered = $factory->customer($title, [
            'firstname' => 'Roger',
            'lastname' => 'Regular',
            'email' => 'registered-badge-probe@test.com',
        ]);

        // Both rows show up and the guest one carries the badge markup.
        $this->client->request('GET', '/admin/customers?q=badge-probe');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('bo-customer-guest', $content, 'The guest badge markup must be present on the list.');
        self::assertStringContainsString('Gustave', $content);
        self::assertStringContainsString('Roger', $content);

        // guest=with isolates the guest customer.
        $this->client->request('GET', '/admin/customers?guest=with&q=badge-probe');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $guestOnly = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Gustave', $guestOnly);
        self::assertStringNotContainsString('Roger', $guestOnly);

        // guest=without isolates the registered customer.
        $this->client->request('GET', '/admin/customers?guest=without&q=badge-probe');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $registeredOnly = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Roger', $registeredOnly);
        self::assertStringNotContainsString('Gustave', $registeredOnly);

        self::assertSame(1, $guest->getIsGuest());
        self::assertSame(0, $registered->getIsGuest());
    }

    public function testCustomerEditPageMentionsGuestAccount(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $title = $factory->customerTitle();
        $guest = $factory->guestCustomer($title, ['email' => 'guest-mention-probe@test.com']);
        $registered = $factory->customer($title, ['email' => 'registered-mention-probe@test.com']);

        $this->client->request('GET', '/admin/customer/update?customer_id='.$guest->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'customer-edit-guest-badge',
            (string) $this->client->getResponse()->getContent(),
            'A guest customer fiche must carry the guest mention.',
        );

        $this->client->request('GET', '/admin/customer/update?customer_id='.$registered->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(
            'customer-edit-guest-badge',
            (string) $this->client->getResponse()->getContent(),
            'A registered customer fiche must not carry the guest mention.',
        );
    }
}

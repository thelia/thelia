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

namespace Thelia\Tests\Integration\Core\Security;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Customer\Service\CustomerGuestConversionService;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What a guest checking out is worth to the security context.
 *
 * The checkout needs a customer to build the order from, so a guest sits in the session
 * under the very key a signed-in customer does — and Customer::getRoles() answers
 * ROLE_CUSTOMER for every row, guest or not. Everything guarded by that role would open
 * to whoever typed an address, unless the guest is set aside before the roles are read.
 */
final class SecurityContextGuestTest extends IntegrationTestCase
{
    private SecurityContext $securityContext;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityContext = $this->getService(SecurityContext::class);
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        $this->securityContext->clearCustomerUser();

        parent::tearDown();
    }

    public function testAGuestNeverSatisfiesTheCustomerRole(): void
    {
        $this->securityContext->setCustomerUser($this->guest());

        self::assertNull(
            $this->securityContext->checkRole(['CUSTOMER']),
            'A guest holds no account, so nothing behind ROLE_CUSTOMER may open for them.',
        );
        self::assertFalse($this->securityContext->isGranted(['CUSTOMER'], [], [], []));
    }

    public function testAGuestThatChoseAPasswordStillNeverSatisfiesTheCustomerRole(): void
    {
        $guest = $this->guest();
        $this->getService(CustomerGuestConversionService::class)->convert($guest, 'a-chosen-password');

        $this->securityContext->setCustomerUser($guest);

        self::assertNull(
            $this->securityContext->checkRole(['CUSTOMER']),
            'The activation code is still unanswered, so the row is still a guest row.',
        );
    }

    public function testASignedInCustomerStillSatisfiesTheCustomerRole(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->securityContext->setCustomerUser($customer);

        $found = $this->securityContext->checkRole(['CUSTOMER']);

        self::assertInstanceOf(Customer::class, $found);
        self::assertSame($customer->getId(), $found->getId());
    }

    public function testTheGuestStateIsReadOffTheRowAndNotOffASessionFlag(): void
    {
        $session = $this->session();
        $session->setCustomerUser($this->guest());

        // The theme still calls this on a session that has just signed in. It must never
        // be able to talk a guest row into being an account.
        $session->setCustomerGuest(false);

        self::assertTrue($session->isCustomerGuest());
        self::assertTrue($this->securityContext->hasGuestCustomerUser());
        self::assertFalse($this->securityContext->hasAuthenticatedCustomerUser());
    }

    public function testASignedInCustomerIsAuthenticatedWhateverTheOldFlagSays(): void
    {
        $session = $this->session();
        $session->setCustomerUser($this->factory->customer($this->factory->customerTitle()));
        $session->setCustomerGuest(true);

        self::assertFalse($session->isCustomerGuest());
        self::assertTrue($this->securityContext->hasAuthenticatedCustomerUser());
        self::assertFalse($this->securityContext->hasGuestCustomerUser());
    }

    private function guest(): Customer
    {
        return $this->factory->guestCustomer(
            $this->factory->customerTitle(),
            ['email' => 'guest-context-'.bin2hex(random_bytes(8)).'@example.com'],
        );
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}

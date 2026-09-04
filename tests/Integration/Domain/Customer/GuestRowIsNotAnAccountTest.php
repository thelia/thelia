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

namespace Thelia\Tests\Integration\Domain\Customer;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Thelia\Core\Security\UserChecker\CustomerUserChecker;
use Thelia\Core\Security\UserProvider\CustomerTokenUserProvider;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;
use Thelia\Domain\Customer\Service\CustomerGuestConversionService;
use Thelia\Domain\Customer\Service\PasswordResetService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A guest row is a row the checkout wrote for whoever typed an address. Nobody proved
 * they own it, and every order ever placed on that address hangs off it.
 *
 * So it is not something anyone signs into, nor something a password reset link may be
 * mailed for, nor something that stands in the way of that address being registered
 * properly. A guest that chose a password is still a guest row until it answers the
 * activation code — the whole point of the code — so every refusal below has to hold
 * after the conversion too.
 */
final class GuestRowIsNotAnAccountTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testTheUserProviderDoesNotFindAGuestRow(): void
    {
        $guest = $this->guest();

        $this->expectException(UserNotFoundException::class);

        (new CustomerUserProvider())->loadUserByIdentifier((string) $guest->getEmail());
    }

    public function testTheUserProviderDoesNotFindAGuestThatChoseAPasswordButNeverAnsweredItsCode(): void
    {
        $guest = $this->guest();
        $this->getService(CustomerGuestConversionService::class)->convert($guest, 'a-chosen-password');

        $this->expectException(UserNotFoundException::class);

        (new CustomerUserProvider())->loadUserByIdentifier((string) $guest->getEmail());
    }

    /**
     * The interaction that makes the rest of this file matter: an address may carry a
     * guest row and a real account at once, because a guest row no longer blocks a
     * registration. Every lookup that means "sign this person in" has to land on the
     * account, never on the guest row that happens to share the address.
     */
    public function testTheUserProviderReturnsTheAccountWhenAnAddressCarriesBoth(): void
    {
        $email = $this->freshEmail();
        $guest = $this->guest($email);
        $account = $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);

        $found = (new CustomerUserProvider())->loadUserByIdentifier($email);

        self::assertInstanceOf(Customer::class, $found);
        self::assertSame($account->getId(), $found->getId());
        self::assertNotSame($guest->getId(), $found->getId());
    }

    public function testRefreshingAGuestOutOfTheSessionFails(): void
    {
        $this->expectException(UserNotFoundException::class);

        (new CustomerUserProvider())->refreshUser($this->guest());
    }

    public function testARememberMeCookieNeverBringsBackAGuestRow(): void
    {
        $guest = $this->guest();
        $guest->setRememberMeSerial('serial-'.bin2hex(random_bytes(4)));
        $guest->setRememberMeToken('token-'.bin2hex(random_bytes(4)));
        $guest->save();

        $this->expectException(\InvalidArgumentException::class);

        (new CustomerTokenUserProvider())->getUser([
            'username' => (string) $guest->getEmail(),
            'serial' => (string) $guest->getRememberMeSerial(),
            'token' => (string) $guest->getRememberMeToken(),
        ]);
    }

    public function testTheUserCheckerRefusesAGuestRow(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new CustomerUserChecker())->checkPreAuth($this->guest());
    }

    public function testTheUserCheckerLetsAnAccountThrough(): void
    {
        (new CustomerUserChecker())
            ->checkPreAuth($this->factory->customer($this->factory->customerTitle()));

        $this->expectNotToPerformAssertions();
    }

    public function testNoPasswordResetLinkIsMailedForAGuestRow(): void
    {
        $storeEmail = ConfigQuery::getStoreEmail();
        // A shop without a sender address sends nothing whatever it is asked to do, and
        // the test database is seeded without one.
        ConfigQuery::write('store_email', 'shop@test.com');

        try {
            $guest = $this->guest();
            $sentBefore = \count($this->sentMessages());

            $this->getService(PasswordResetService::class)->requestResetLink((string) $guest->getEmail());

            self::assertCount(
                0,
                \array_slice($this->sentMessages(), $sentBefore),
                'A guest row has no password to reset, and no owner anybody has verified.',
            );
        } finally {
            ConfigQuery::write('store_email', (string) $storeEmail);
        }
    }

    /**
     * What the three email uniqueness checks of the shop are built on. A guest row must
     * not answer "this email already exists" — the visitor it belongs to never registered.
     */
    public function testLookingUpTheAccountBehindAnAddressIgnoresGuestRows(): void
    {
        $guest = $this->guest();

        self::assertNull(CustomerQuery::getCustomerByEmail((string) $guest->getEmail()));
    }

    public function testLookingUpTheAccountBehindAnAddressFindsTheRealOne(): void
    {
        $email = $this->freshEmail();
        $this->guest($email);
        $account = $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);

        self::assertSame($account->getId(), CustomerQuery::getCustomerByEmail($email)?->getId());
    }

    private function guest(?string $email = null): Customer
    {
        return $this->factory->guestCustomer(
            $this->factory->customerTitle(),
            ['email' => $email ?? $this->freshEmail()],
        );
    }

    private function freshEmail(): string
    {
        return 'guest-row-'.bin2hex(random_bytes(8)).'@example.com';
    }

    /**
     * @return list<\Symfony\Component\Mime\RawMessage>
     */
    private function sentMessages(): array
    {
        return $this->getService('mailer.message_logger_listener')->getEvents()->getMessages();
    }
}

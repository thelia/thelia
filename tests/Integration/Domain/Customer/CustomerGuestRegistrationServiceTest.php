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

use Thelia\Domain\Customer\DTO\CustomerGuestDTO;
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;
use Thelia\Domain\Customer\Service\CustomerGuestRegistrationService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A guest is a customer row with no password, opened only to carry an order.
 *
 * Two things must hold whatever the visitor types: the shop never invents a password
 * for an identity nobody chose to protect, and an address that already belongs to a
 * real account never gets an order attached to it by someone who merely knows it.
 */
final class CustomerGuestRegistrationServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CustomerGuestRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->service = $this->getService(CustomerGuestRegistrationService::class);
    }

    public function testTheGuestIsOpenedWithoutAPassword(): void
    {
        $email = $this->freshEmail();

        $guest = $this->service->registerGuest(new CustomerGuestDTO(
            email: $email,
            firstname: 'Ada',
            lastname: 'Lovelace',
            title: $this->factory->customerTitle()->getId(),
        ));

        $stored = $this->reload($guest);

        self::assertTrue($stored->isGuest(), 'The row must be marked as a guest.');
        self::assertSame($email, $stored->getEmail());
        self::assertSame('Ada', $stored->getFirstname());
        self::assertSame('Lovelace', $stored->getLastname());
        // The model answers '' for an account with no password, so the column itself is
        // what says whether the shop hashed and stored anything.
        self::assertNull($this->storedPassword($stored), 'A guest must carry no password at all.');
        self::assertNull($stored->getAlgo(), 'Nothing was hashed, so no algorithm was recorded.');
        self::assertFalse($stored->checkPassword(''), 'The empty password must authenticate nobody.');
    }

    /**
     * The visitor asked for no account, so the shop must not announce one — not even on
     * a shop that confirms every address it is given, where a registration is mailed an
     * activation code. There is nothing here to activate.
     */
    public function testOpeningAGuestMailsNothing(): void
    {
        $emailConfirmationWasEnabled = ConfigQuery::isCustomerEmailConfirmationEnable();
        $storeEmail = ConfigQuery::getStoreEmail();

        ConfigQuery::write('customer_email_confirmation', '1');
        // A shop without a sender address sends nothing at all whatever it is asked to
        // do, and the test database is seeded without one: without this the assertion
        // below would hold even for code that does mail the guest.
        ConfigQuery::write('store_email', 'shop@test.com');

        try {
            $sentBefore = \count($this->sentMessages());

            $guest = $this->service->registerGuest(new CustomerGuestDTO(
                email: $this->freshEmail(),
                firstname: 'Silent',
                lastname: 'Guest',
                title: $this->factory->customerTitle()->getId(),
            ));

            self::assertCount(
                $sentBefore,
                $this->sentMessages(),
                'Opening a guest must send no email of its own.',
            );
            self::assertNull(
                $this->reload($guest)->getConfirmationToken(),
                'A guest has no account to confirm, so no activation code is waiting on it.',
            );
        } finally {
            ConfigQuery::write('customer_email_confirmation', $emailConfirmationWasEnabled ? '1' : '0');
            ConfigQuery::write('store_email', (string) $storeEmail);
        }
    }

    public function testOrderingAgainWithTheSameAddressReusesTheGuest(): void
    {
        $email = $this->freshEmail();
        $titleId = $this->factory->customerTitle()->getId();

        $first = $this->service->registerGuest(new CustomerGuestDTO(
            email: $email,
            firstname: 'Ada',
            lastname: 'Lovelace',
            title: $titleId,
        ));

        $second = $this->service->registerGuest(new CustomerGuestDTO(
            email: $email,
            firstname: 'Augusta',
            lastname: 'King',
            title: $titleId,
        ));

        self::assertSame(
            $first->getId(),
            $second->getId(),
            'One address must not end up with the orders split over two guest rows.',
        );
        self::assertSame(
            1,
            CustomerQuery::create()->filterByEmail($email)->count(),
            'No second row may be created for the same address.',
        );

        $stored = $this->reload($second);
        self::assertSame('Augusta', $stored->getFirstname(), 'The name just typed wins.');
        self::assertSame('King', $stored->getLastname());
    }

    /**
     * The address is stored and compared in one form, the same one the rate limiter
     * counts in. Two spellings of the same mailbox are one guest, one budget, one set of
     * orders — otherwise a capital letter buys a fresh row and a fresh budget.
     */
    public function testTheAddressIsNormalisedBeforeAnythingIsLookedUp(): void
    {
        $email = $this->freshEmail();
        $titleId = $this->factory->customerTitle()->getId();

        $first = $this->service->registerGuest(new CustomerGuestDTO(
            email: $email,
            firstname: 'Ada',
            lastname: 'Lovelace',
            title: $titleId,
        ));

        $second = $this->service->registerGuest(new CustomerGuestDTO(
            email: '  '.strtoupper($email).' ',
            firstname: 'Ada',
            lastname: 'Lovelace',
            title: $titleId,
        ));

        self::assertSame($first->getId(), $second->getId(), 'One mailbox, one guest row.');
        self::assertSame($email, $this->reload($first)->getEmail(), 'And it is stored in the normalised form.');
    }

    public function testAnAddressThatAlreadyHasAnAccountIsRefused(): void
    {
        $email = $this->freshEmail();
        $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);

        $this->expectException(GuestCheckoutEmailAlreadyRegisteredException::class);

        $this->service->registerGuest(new CustomerGuestDTO(
            email: $email,
            firstname: 'Not',
            lastname: 'TheOwner',
            title: $this->factory->customerTitle()->getId(),
        ));
    }

    /**
     * The address is the only thing the shop can reach a guest by, and the only thing
     * a returning guest is recognised on.
     */
    public function testAGuestWithoutAnAddressIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->registerGuest(new CustomerGuestDTO(
            firstname: 'No',
            lastname: 'Address',
            title: $this->factory->customerTitle()->getId(),
        ));
    }

    /**
     * @return list<\Symfony\Component\Mime\RawMessage>
     */
    private function sentMessages(): array
    {
        return $this->getService('mailer.message_logger_listener')->getEvents()->getMessages();
    }

    private function storedPassword(Customer $customer): ?string
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `password` FROM `customer` WHERE `id` = :id');
        $statement->execute(['id' => $customer->getId()]);

        return $statement->fetch(\PDO::FETCH_ASSOC)['password'] ?? null;
    }

    private function freshEmail(): string
    {
        return 'guest-registration-'.bin2hex(random_bytes(8)).'@example.com';
    }

    private function reload(Customer $customer): Customer
    {
        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertInstanceOf(Customer::class, $reloaded);

        return $reloaded;
    }
}

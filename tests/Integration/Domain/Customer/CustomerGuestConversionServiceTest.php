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

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;
use Thelia\Domain\Customer\Exception\NotAGuestCustomerException;
use Thelia\Domain\Customer\Service\CustomerCodeManager;
use Thelia\Domain\Customer\Service\CustomerGuestConversionService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Someone who ordered as a guest may keep the account that carried the order, by
 * giving it the password it never had.
 *
 * The account is kept rather than recreated, so the orders stay where they are. Choosing
 * the password is not the end of it: the row stays a guest row, opening nothing, until
 * the activation code mailed here is answered. What must never happen is this working on
 * an address whose real account belongs to somebody else — that would be a password
 * reset without any of a reset's guarantees.
 */
final class CustomerGuestConversionServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CustomerGuestConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->service = $this->getService(CustomerGuestConversionService::class);
    }

    public function testTheGuestKeepsTheAccountAndGainsAPassword(): void
    {
        $guest = $this->guest();
        $guestId = $guest->getId();

        $converted = $this->service->convert($guest, 'a-chosen-password');

        self::assertSame($guestId, $converted->getId(), 'The account carrying the orders must be the one kept.');

        $stored = $this->reload($converted);
        self::assertTrue($stored->checkPassword('a-chosen-password'), 'The chosen password must be the one stored.');
        self::assertSame('PASSWORD_BCRYPT', $stored->getAlgo());
    }

    /**
     * Choosing a password proves nothing — anyone can order as a guest on an address they
     * do not own, and the row carries every order ever placed on it. Until the code is
     * answered the row is still a guest, which is what keeps it out of the sign-in, the
     * password reset and every account page.
     */
    public function testTheRowStaysAGuestUntilTheCodeIsAnswered(): void
    {
        $converted = $this->service->convert($this->guest(), 'a-chosen-password');

        self::assertTrue(
            $this->reload($converted)->isGuest(),
            'A password without the code answered must leave the row a guest.',
        );
    }

    /**
     * The guest row is shared by everyone who ever ordered on that address, so whoever
     * completes it inherits the orders already on it. Reading the mailbox is the only
     * thing that separates the person those orders belong to from someone who merely
     * knows the address — which is why the shop's "confirm every address" setting has no
     * say here: it decides how much a fresh registration is trusted, and this is not one.
     *
     * @testWith [true]
     *           [false]
     */
    public function testTheAccountWaitsForItsCodeWhateverTheShopConfirms(bool $shopConfirmsAddresses): void
    {
        $this->withEmailConfirmation($shopConfirmsAddresses, function (): void {
            $converted = $this->service->convert($this->guest(), 'a-chosen-password');

            $stored = $this->reload($converted);
            self::assertFalse((bool) $stored->getEnable(), 'The account must not be usable before its owner answers the code.');
            self::assertNotNull($stored->getConfirmationToken(), 'An activation code must be waiting.');
        });
    }

    /**
     * The code is worth nothing if it never leaves. SEND_ACCOUNT_CONFIRMATION_EMAIL used
     * to carry it, and its listener stops on the shop's setting: on a shop that does not
     * confirm addresses the account came out disabled with nothing ever mailed to open it.
     *
     * @testWith [true]
     *           [false]
     */
    public function testTheCodeIsMailedWhateverTheShopConfirms(bool $shopConfirmsAddresses): void
    {
        $storeEmail = ConfigQuery::getStoreEmail();
        // A shop without a sender address sends nothing at all whatever it is asked to
        // do, and the test database is seeded without one.
        ConfigQuery::write('store_email', 'shop@test.com');

        try {
            $this->withEmailConfirmation($shopConfirmsAddresses, function (): void {
                $guest = $this->guest();
                $sentBefore = \count($this->sentMessages());

                $this->service->convert($guest, 'a-chosen-password');

                $sent = \array_slice($this->sentMessages(), $sentBefore);

                self::assertCount(1, $sent, 'Completing the account must mail exactly one activation code.');

                $mail = $sent[0];
                self::assertInstanceOf(Email::class, $mail);
                self::assertSame(
                    [$guest->getEmail()],
                    array_map(static fn (Address $address): string => $address->getAddress(), $mail->getTo()),
                    'The code must go to the address the account is being opened on.',
                );

                // The column holds a salted hash, so what proves the mail is usable is
                // the code in it opening the account through the activation flow.
                self::assertSame(
                    1,
                    preg_match('/\b\d{6}\b/', $mail->getTextBody(), $matches),
                    'The mail must carry the activation code.',
                );

                $this->getService(CustomerCodeManager::class)
                    ->activateCustomerByCode((string) $guest->getEmail(), $matches[0]);

                $activated = $this->reload($guest);
                self::assertTrue(
                    (bool) $activated->getEnable(),
                    'The code that was mailed must be the one the account accepts.',
                );
                self::assertFalse(
                    $activated->isGuest(),
                    'Answering the code is what turns the guest row into an account.',
                );
            });
        } finally {
            ConfigQuery::write('store_email', (string) $storeEmail);
        }
    }

    /**
     * The buyer chose a password, never opened the mail, and asks again — a second tab, a
     * new device, a forgotten password before the account even existed. The row is still
     * a guest, so this has to work: refusing would leave them with an address they cannot
     * register and an account they cannot open.
     *
     * Nothing is given away by it. Neither password opens anything until a code is
     * answered, and the code goes to the mailbox either way.
     */
    public function testAGuestThatNeverAnsweredItsCodeMayChooseAnotherPassword(): void
    {
        $guest = $this->guest();

        $this->service->convert($guest, 'a-first-password');
        $this->service->convert($guest, 'a-second-password');

        $stored = $this->reload($guest);
        self::assertTrue($stored->checkPassword('a-second-password'), 'The last password chosen is the one kept.');
        self::assertFalse($stored->checkPassword('a-first-password'), 'The earlier password must be gone.');
        self::assertTrue($stored->isGuest(), 'Still a guest: no code has been answered yet.');
    }

    public function testAnAccountThatAnsweredItsCodeCannotBeConvertedAgain(): void
    {
        $guest = $this->guest();

        $this->service->convert($guest, 'a-chosen-password');
        $guest->setIsGuest(0)->setEnable(1)->save();

        $this->expectException(NotAGuestCustomerException::class);
        $this->service->convert($guest, 'someone-elses-password');
    }

    public function testConvertingAnAccountThatWasNeverAGuestIsRefused(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->expectException(NotAGuestCustomerException::class);
        $this->service->convert($customer, 'someone-elses-password');
    }

    /**
     * A real account may have taken the address between the guest order and the moment
     * the guest comes back to complete it.
     */
    public function testConvertingIsRefusedWhenTheAddressWasTakenMeanwhile(): void
    {
        $email = $this->freshEmail();
        $guest = $this->guest($email);
        $this->factory->customer($this->factory->customerTitle(), ['email' => $email]);

        $this->expectException(GuestCheckoutEmailAlreadyRegisteredException::class);
        $this->service->convert($guest, 'a-chosen-password');
    }

    public function testAnEmptyPasswordIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->convert($this->guest(), '   ');
    }

    private function guest(?string $email = null): Customer
    {
        return $this->factory->guestCustomer(
            $this->factory->customerTitle(),
            ['email' => $email ?? $this->freshEmail()],
        );
    }

    private function withEmailConfirmation(bool $enabled, callable $test): void
    {
        $wasEnabled = ConfigQuery::isCustomerEmailConfirmationEnable();
        ConfigQuery::write('customer_email_confirmation', $enabled ? '1' : '0');

        try {
            $test();
        } finally {
            ConfigQuery::write('customer_email_confirmation', $wasEnabled ? '1' : '0');
        }
    }

    /**
     * @return list<\Symfony\Component\Mime\RawMessage>
     */
    private function sentMessages(): array
    {
        return $this->getService('mailer.message_logger_listener')->getEvents()->getMessages();
    }

    private function freshEmail(): string
    {
        return 'guest-conversion-'.bin2hex(random_bytes(8)).'@example.com';
    }

    private function reload(Customer $customer): Customer
    {
        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertInstanceOf(Customer::class, $reloaded);

        return $reloaded;
    }
}

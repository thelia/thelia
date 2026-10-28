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

namespace Thelia\Tests\Integration\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Exception\InvalidCustomerDiscountException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

final class CustomerActionTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testCreateMinimalCustomerPersistsWithHashedPassword(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setEmail('jane.doe@test.com')
            ->setPassword('secret123');

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $customer = $event->getCustomer();
        self::assertNotNull($customer);
        self::assertNotNull($customer->getId());

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Jane', $reloaded->getFirstname());
        self::assertSame('Doe', $reloaded->getLastname());
        self::assertSame('jane.doe@test.com', $reloaded->getEmail());

        self::assertNotSame('secret123', $reloaded->getPassword());
        self::assertTrue(password_verify('secret123', $reloaded->getPassword()));
        self::assertSame('PASSWORD_BCRYPT', $reloaded->getAlgo());
    }

    public function testCreateMinimalCustomerWithDiscount(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('John')
            ->setLastname('Discount')
            ->setEmail('discount@test.com')
            ->setPassword('password')
            ->setDiscount(15.5)
            ->setReseller(true);

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $customer = $event->getCustomer();
        self::assertNotNull($customer);
        self::assertEqualsWithDelta(15.5, (float) $customer->getDiscount(), 0.01);
        self::assertTrue((bool) $customer->getReseller());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function discountsOutsideAPercentage(): iterable
    {
        yield 'above 100' => [100.01];
        yield 'negative' => [-0.5];
    }

    #[DataProvider('discountsOutsideAPercentage')]
    public function testADiscountOutsideZeroToOneHundredIsRefusedBeforeAnythingIsWritten(float $discount): void
    {
        $title = $this->factory->customerTitle();
        $customerCount = CustomerQuery::create()->count();

        $minimal = (new CustomerCreateOrUpdateMinimalEvent())
            ->setTitle($title->getId())
            ->setFirstname('John')
            ->setLastname('Generous')
            ->setEmail('generous@test.com')
            ->setPassword('password')
            ->setDiscount($discount);

        try {
            $this->dispatcher->dispatch($minimal, TheliaEvents::CREATE_CUSTOMER_MINIMAL);
            self::fail('A discount of '.$discount.' was accepted at the registration.');
        } catch (InvalidCustomerDiscountException) {
        }

        $customer = $this->factory->customer($title);
        $update = (new CustomerCreateOrUpdateEvent(
            title: $title->getId(),
            firstname: 'Changed',
            lastname: 'Name',
            discount: $discount,
        ))->setCustomer($customer);

        foreach ([TheliaEvents::CUSTOMER_UPDATEACCOUNT, TheliaEvents::CUSTOMER_UPDATEPROFILE] as $eventName) {
            try {
                $this->dispatcher->dispatch($update, $eventName);
                self::fail('A discount of '.$discount.' was accepted by '.$eventName.'.');
            } catch (InvalidCustomerDiscountException) {
            }
        }

        self::assertSame($customerCount + 1, CustomerQuery::create()->count(), 'only the fixture customer exists');
        self::assertSame('John', CustomerQuery::create()->findPk($customer->getId())?->getFirstname(), 'the account is left as it was');
        self::assertSame(0.0, (float) CustomerQuery::create()->findPk($customer->getId())?->getDiscount());
    }

    public function testAccountConfirmationEmailCarriesTheActivationCode(): void
    {
        $emailConfirmationWasEnabled = ConfigQuery::isCustomerEmailConfirmationEnable();
        $storeEmail = ConfigQuery::getStoreEmail();

        ConfigQuery::write('customer_email_confirmation', '1');
        // A shop without a sender address sends nothing at all, and the test
        // database is seeded without one.
        ConfigQuery::write('store_email', 'shop@test.com');

        try {
            $event = new CustomerCreateOrUpdateMinimalEvent();
            $event
                ->setTitle($this->factory->customerTitle()->getId())
                ->setFirstname('Ada')
                ->setLastname('Confirm')
                ->setEmail('activation.code@test.com')
                ->setPassword('Str0ng-Passw0rd!2026');

            $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

            $customer = $event->getCustomer();
            self::assertNotNull($customer);
            self::assertNotNull($customer->getConfirmationToken(), 'The account is waiting for its activation code');

            $sentEmails = $this->getService('mailer.message_logger_listener')->getEvents()->getMessages();
            self::assertCount(1, $sentEmails, 'One registration sends one email');

            $email = $sentEmails[0];

            // The subject comes from the message_i18n row of customer_send_code: an
            // account created on a shop whose seed lacks that row mails an empty one.
            self::assertNotSame('', (string) $email->getSubject(), 'The activation email has a subject');
            self::assertMatchesRegularExpression(
                '/\b\d{'.Customer::CODE_LENGTH.'}\b/',
                (string) $email->getTextBody(),
                'The activation email carries the code the activation page asks for',
            );
        } finally {
            ConfigQuery::write('customer_email_confirmation', $emailConfirmationWasEnabled ? '1' : '0');
            ConfigQuery::write('store_email', (string) $storeEmail);
        }
    }

    public function testUpdateAccountOfCustomerWithoutAddressCreatesTheDefaultAddress(): void
    {
        $title = $this->factory->customerTitle();
        $country = $this->factory->country();
        $customer = $this->factory->customer($title);

        self::assertNull($customer->getDefaultAddress());

        $event = new CustomerCreateOrUpdateEvent(
            title: $title->getId(),
            firstname: 'Jean-Marie',
            lastname: 'Rudler',
            address1: '1 rue des Lilas',
            zipcode: '24190',
            city: 'Vallereuil',
            country: (string) $country->getId(),
            email: $customer->getEmail(),
            reseller: false,
        );
        $event->setCustomer($customer);
        $event->setNotifyCustomerOfAccountModification(false);

        $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

        $address = CustomerQuery::create()->findPk($customer->getId())->getDefaultAddress();
        self::assertNotNull($address);
        self::assertSame('1 rue des Lilas', $address->getAddress1());
        self::assertSame('Vallereuil', $address->getCity());
        self::assertSame($country->getId(), $address->getCountryId());
    }

    public function testTheAccountCreatedMailDoesNotCarryThePassword(): void
    {
        $title = $this->factory->customerTitle();
        $country = $this->factory->country();
        $plainPassword = 'Cleartext-Secret-4207';

        $this->withAShopSenderAddress(function () use ($title, $country, $plainPassword): void {
            $event = new CustomerCreateOrUpdateEvent(
                title: $title->getId(),
                firstname: 'Ada',
                lastname: 'Created',
                address1: '1 rue des Lilas',
                zipcode: '75001',
                city: 'Paris',
                country: (string) $country->getId(),
                email: 'created.by.admin@test.com',
                password: $plainPassword,
                reseller: false,
            );
            $event->setNotifyCustomerOfAccountCreation(true);

            $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_CREATEACCOUNT);

            $email = $this->theOnlyMailSentTo('created.by.admin@test.com');
            self::assertStringNotContainsString($plainPassword, (string) $email->getTextBody());
            self::assertStringNotContainsString($plainPassword, (string) $email->getHtmlBody());
        });
    }

    public function testTheAccountChangedMailDoesNotCarryTheNewPassword(): void
    {
        $title = $this->factory->customerTitle();
        $country = $this->factory->country();
        $customer = $this->factory->customer($title);
        $plainPassword = 'Cleartext-Secret-4208';

        $this->withAShopSenderAddress(function () use ($title, $country, $customer, $plainPassword): void {
            $event = new CustomerCreateOrUpdateEvent(
                title: $title->getId(),
                firstname: 'Ada',
                lastname: 'Changed',
                address1: '1 rue des Lilas',
                zipcode: '75001',
                city: 'Paris',
                country: (string) $country->getId(),
                email: $customer->getEmail(),
                password: $plainPassword,
                reseller: false,
            );
            $event->setCustomer($customer);

            $this->dispatcher->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

            $email = $this->theOnlyMailSentTo($customer->getEmail());
            self::assertStringNotContainsString($plainPassword, (string) $email->getTextBody());
            self::assertStringNotContainsString($plainPassword, (string) $email->getHtmlBody());
        });

        self::assertTrue(password_verify($plainPassword, CustomerQuery::create()->findPk($customer->getId())->getPassword()));
    }

    private function withAShopSenderAddress(callable $test): void
    {
        // A shop without a sender address sends nothing at all, and the test
        // database is seeded without one.
        $storeEmail = ConfigQuery::getStoreEmail();
        ConfigQuery::write('store_email', 'shop@test.com');

        try {
            $test();
        } finally {
            ConfigQuery::write('store_email', (string) $storeEmail);
        }
    }

    private function theOnlyMailSentTo(string $address): Email
    {
        $sentEmails = array_values(array_filter(
            self::getMailerMessages(),
            static fn (RawMessage $message): bool => $message instanceof Email
                && \in_array($address, array_map(static fn (Address $to): string => $to->getAddress(), $message->getTo()), true),
        ));

        self::assertCount(1, $sentEmails, 'The account mail is sent');

        return $sentEmails[0];
    }

    public function testTransactionRollbackIsolatesTests(): void
    {
        $title = $this->factory->customerTitle();

        $event = new CustomerCreateOrUpdateMinimalEvent();
        $event
            ->setTitle($title->getId())
            ->setFirstname('Isolated')
            ->setLastname('Test')
            ->setEmail('isolated@test.com')
            ->setPassword('password');

        $this->dispatcher->dispatch($event, TheliaEvents::CREATE_CUSTOMER_MINIMAL);

        $id = $event->getCustomer()->getId();
        self::assertNotNull(CustomerQuery::create()->findPk($id));

        // This customer will be rolled back in tearDown().
        // The next test should not see it.
    }

    public function testPreviousTestDataIsRolledBack(): void
    {
        $result = CustomerQuery::create()
            ->filterByEmail('isolated@test.com')
            ->findOne();

        self::assertNull($result, 'Transaction rollback should have removed the customer from the previous test');
    }

    public function testLogoutRetiresTheRememberMeToken(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setRememberMeToken('issued-at-login')->save();
        $this->loginAsCustomerInSession($customer);

        $this->dispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::CUSTOMER_LOGOUT);

        self::assertNull(CustomerQuery::create()->findPk($customer->getId())->getRememberMeToken());
    }

    public function testANewPasswordRetiresTheRememberMeToken(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setRememberMeToken('issued-under-the-old-password')->save();

        $customer->setPassword('brand-new-password')->save();

        self::assertNull(CustomerQuery::create()->findPk($customer->getId())->getRememberMeToken());
    }
}

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

use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Opening an account on the address an order was already placed under.
 *
 * The buyer who ordered without an account and then signs up from the shop expects to
 * find that order waiting for them, and that is what the feature promises. Their history
 * hangs off the row the order was placed under, so the registration completes that row
 * instead of opening a second one beside it — two rows on one address would split the
 * history in half and show the shop two customers with the same name.
 */
final class RegistrationOnAGuestAddressTest extends IntegrationTestCase
{
    private const EMAIL = 'ordered-then-registered@test.com';

    private const PASSWORD = 'a-password-of-their-own';

    public function testRegisteringOnTheAddressOfAGuestOrderCompletesThatRecord(): void
    {
        $guest = $this->aGuestWhoAlreadyOrdered();

        $registered = $this->register();

        self::assertSame(
            $guest->getId(),
            $registered->getId(),
            'The registration must complete the record the orders hang off, not open another.',
        );
        self::assertCount(
            1,
            CustomerQuery::create()->filterByEmail(self::EMAIL)->find(),
            'One address, one person, one record.',
        );
    }

    public function testTheOrderPlacedWithoutAnAccountStaysOnTheRecord(): void
    {
        $guest = $this->aGuestWhoAlreadyOrdered();
        $orderId = $this->orderOf($guest)->getId();

        $registered = $this->register();

        self::assertSame(
            $registered->getId(),
            OrderQuery::create()->findPk($orderId)?->getCustomerId(),
            'The order the buyer placed before signing up must be in the history they sign in to.',
        );
    }

    /**
     * The account is not open on the strength of a signup form alone: ordering without
     * an account is open to anyone, so the record may carry somebody else's orders, and
     * what separates the two is reading the mailbox. The activation code decides, exactly
     * as it does when the account is completed from the order tracking link.
     */
    public function testTheRecordIsOnlyOpenedOnceTheActivationCodeIsAnswered(): void
    {
        $this->aGuestWhoAlreadyOrdered();

        $registered = $this->register();

        self::assertNotSame('', $registered->getPassword(), 'The password the buyer chose must be on the record.');
        self::assertTrue($registered->isGuest(), 'The record is opened by the activation code, not by the form.');
        self::assertSame(0, $registered->getEnable(), 'And it stays closed until then.');
    }

    private function aGuestWhoAlreadyOrdered(): Customer
    {
        $fixtures = $this->createFixtureFactory();
        $guest = $fixtures->guestCustomer($fixtures->customerTitle(), ['email' => self::EMAIL]);

        $fixtures->order($guest);

        return $guest;
    }

    private function orderOf(Customer $guest): Order
    {
        $order = OrderQuery::create()->filterByCustomerId($guest->getId())->findOne();

        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    private function register(): Customer
    {
        /** @var CustomerRegistrationService $registration */
        $registration = $this->getService(CustomerRegistrationService::class);

        return $registration->registerCustomer(new CustomerRegisterDTO(
            firstname: 'Camille',
            lastname: 'Durand',
            email: self::EMAIL,
            password: self::PASSWORD,
        ));
    }
}

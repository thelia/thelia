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

namespace Thelia\Domain\Customer\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerGuestCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\DTO\CustomerGuestDTO;
use Thelia\Domain\Customer\EmailAddress;
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;

/**
 * Gives an order placed without an account the customer row it hangs off.
 *
 * A visitor who orders as a guest twice with the same address gets the same guest
 * row back, name and language refreshed from what they just typed: two rows would
 * split one person's orders in half, and the second one would collide with the
 * first the day either of them is converted into a real account.
 *
 * An address that already belongs to a real account is refused outright — see
 * {@see GuestCheckoutEmailAlreadyRegisteredException}. An address that only carries a
 * guest row is not one of those: nobody proved they own it, so it is reused, not refused.
 * A guest that chose a password but never answered its activation code is still a guest
 * row and behaves the same way.
 */
final readonly class CustomerGuestRegistrationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws GuestCheckoutEmailAlreadyRegisteredException when the address belongs to a real account
     * @throws PropelException
     */
    public function registerGuest(CustomerGuestDTO $guest): Customer
    {
        $email = EmailAddress::normalize($guest->getEmail());

        if ('' === $email) {
            throw new \InvalidArgumentException('An email address is required to order without an account.');
        }

        // "Does this address have a row" and "give it one" have to be one step, or two
        // visitors typing the same address at the same moment both read no row and both
        // write one — and the day either of them completes the account, the other's
        // orders are on the row nobody completed. A unique index on customer.email would
        // settle it for good, but the column is not unique in the installed schema and
        // shops carry duplicates from before the guest checkout existed, so this narrows
        // the window rather than closing it.
        $connection = Propel::getConnection(CustomerTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            if (null !== $this->findRegisteredAccount($email, $connection)) {
                throw new GuestCheckoutEmailAlreadyRegisteredException('This email address already has an account. Sign in to place the order.');
            }

            $existingGuest = $this->findReusableGuest($email, $connection);

            $customer = $existingGuest instanceof Customer
                ? $this->refresh($existingGuest, $guest, $connection)
                : $this->create($guest, $email);

            $connection->commit();

            return $customer;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * @throws PropelException
     */
    private function create(CustomerGuestDTO $guest, string $normalizedEmail): Customer
    {
        // Only what the visitor actually gave: bindArray() casts a null onto a
        // non-nullable setter rather than skipping it, which would turn a field that
        // was never filled in into an empty string. The address goes in normalized, so
        // that the row is stored under the very value the next visit looks it up by.
        $event = (new CustomerGuestCreateEvent())->bindArray(
            array_filter(
                [...$guest->toArray(), 'email' => $normalizedEmail],
                static fn (mixed $value): bool => null !== $value,
            ),
        );

        $this->eventDispatcher->dispatch($event, TheliaEvents::CUSTOMER_GUEST_CREATE);

        $customer = $event->getCustomer();

        if (!$customer instanceof Customer) {
            throw new \RuntimeException('Guest creation failed, no customer returned from event.');
        }

        return $customer;
    }

    /**
     * The most recent guest row for that address, if there is one.
     *
     * Ordering by id rather than by creation date: two rows created in the same second
     * would otherwise come back in an order the database is free to choose.
     */
    private function findReusableGuest(string $email, ConnectionInterface $connection): ?Customer
    {
        return CustomerQuery::create()
            ->filterByEmail($email)
            ->filterByIsGuest(1)
            ->filterByAnonymizedAt(null, Criteria::ISNULL)
            ->orderById(Criteria::DESC)
            ->findOne($connection);
    }

    /**
     * The account registered on that address, guest rows excluded.
     *
     * A guest row is not an account: it does not stand in the way of a guest checkout,
     * and it is not what "this address already has an account" means. A converted guest
     * still counts as a guest row here until its activation code is answered — which is
     * deliberate: until then nobody has proved they own the address, so a second visitor
     * ordering on it lands on that same row, exactly as before the conversion.
     */
    private function findRegisteredAccount(string $email, ConnectionInterface $connection): ?Customer
    {
        return CustomerQuery::create()
            ->filterByEmail($email)
            ->filterByIsGuest(0)
            ->findOne($connection);
    }

    /**
     * @throws PropelException
     */
    private function refresh(Customer $customer, CustomerGuestDTO $guest, ConnectionInterface $connection): Customer
    {
        if (null !== $guest->getFirstname()) {
            $customer->setFirstname($guest->getFirstname());
        }

        if (null !== $guest->getLastname()) {
            $customer->setLastname($guest->getLastname());
        }

        if (null !== $guest->getTitle()) {
            $customer->setTitleId($guest->getTitle());
        }

        if (null !== $guest->getLangId()) {
            $customer->setLangId($guest->getLangId());
        }

        $customer->save($connection);

        return $customer;
    }
}

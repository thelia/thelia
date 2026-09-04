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
use Thelia\Domain\Customer\Exception\GuestCheckoutEmailAlreadyRegisteredException;
use Thelia\Domain\Customer\Exception\NotAGuestCustomerException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;

/**
 * Turns the passwordless account behind a guest order into a real one.
 *
 * The account is kept, not recreated: the orders, the addresses and the history
 * already hang off it, and moving them would break every reference the shop and the
 * accounting keep. All that changes here is that it gains a password — which already
 * invalidates the order tracking links issued while it had none, since those are signed
 * against the password hash.
 *
 * What it does *not* do is stop being a guest. The row carries `is_guest = 1` until the
 * activation code mailed below is answered, and that is the whole point: between the
 * password being chosen and the mailbox being read, nobody has proved anything, and a
 * row that is still a guest is a row that cannot sign in, cannot be sent a password
 * reset link, and opens no account page. {@see CustomerCodeManager::activateCustomerByCode()}
 * is where the row stops being a guest.
 *
 * A guest who chose a password and never answered the code may choose another one: the
 * row is still a guest, so this runs again, replaces the password and mails a fresh
 * code. Nothing is lost by it — neither password opens anything until a code is answered.
 *
 * The account always comes out disabled, and the shop's own "confirm every address"
 * setting has no say in it. That setting decides how much a *fresh* registration is
 * trusted, and this is not one: one guest row is shared by everyone who ever ordered on
 * that address, so whoever completes it inherits the orders of whoever ordered before
 * them. Anybody can put an address they do not own into that row — ordering as a guest
 * is open to all — so the only thing that separates the person the orders belong to from
 * someone who merely typed their address is reading the mailbox. Handing over an enabled
 * account on a shop that does not confirm addresses would hand over that history for the
 * price of knowing an email.
 */
final readonly class CustomerGuestConversionService
{
    public function __construct(
        private CustomerCodeManager $customerCodeManager,
    ) {
    }

    /**
     * @throws NotAGuestCustomerException                   when the row is an account someone already activated
     * @throws GuestCheckoutEmailAlreadyRegisteredException when a real account took the address in the meantime
     * @throws PropelException
     */
    public function convert(Customer $customer, string $plainPassword): Customer
    {
        if (!$customer->isGuest()) {
            throw new NotAGuestCustomerException('This account is not a guest account and cannot be converted.');
        }

        if ('' === trim($plainPassword)) {
            throw new \InvalidArgumentException('A password is required to turn a guest into an account.');
        }

        // "Is the address free" and "take it" have to be one step, or two conversions
        // racing on the same address both read a free address and both write a password.
        // A unique index on customer.email would settle it for good, but the column is
        // not unique in the installed schema and shops carry duplicates from before the
        // guest checkout existed, so this narrows the window rather than closing it.
        $connection = Propel::getConnection(CustomerTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            if ($this->addressTakenByAnotherAccount($customer, $connection)) {
                throw new GuestCheckoutEmailAlreadyRegisteredException('This email address already has an account. Sign in to it instead.');
            }

            // The account is no longer new, so setPassword() hashes and stores it with no
            // guest exemption involved. is_guest is deliberately left alone: the row stops
            // being a guest when the code below is answered, not when it is sent.
            $customer
                ->setPassword($plainPassword)
                ->setEnable(0)
                ->save($connection);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        // Outside the transaction: a mail cannot be taken back, and one carrying a code
        // for a password that was rolled back would be worse than none.
        //
        // Not SEND_ACCOUNT_CONFIRMATION_EMAIL: its listener stops on the shop's
        // confirmation setting, so on a shop that does not confirm addresses it would
        // leave the account disabled with nothing ever mailed to open it. This is the
        // same code, and the same mail, the activation pages hand out on demand.
        $this->customerCodeManager->createCodeAndSendIt($customer);

        return $customer;
    }

    /**
     * @throws PropelException
     */
    private function addressTakenByAnotherAccount(Customer $customer, ConnectionInterface $connection): bool
    {
        return null !== CustomerQuery::create()
            ->filterByEmail($customer->getEmail())
            ->filterByIsGuest(0)
            ->filterById($customer->getId(), Criteria::NOT_EQUAL)
            ->findOne($connection);
    }
}

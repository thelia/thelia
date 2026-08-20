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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AddressQuery;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerVersionQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;

/**
 * Erases the identifying data of a customer while keeping the accounting
 * record of the orders intact.
 *
 * Orders stay attached to the (now anonymous) customer account, keep their
 * reference, their invoice number and date, their amounts, their taxes, their
 * coupons and their status history. What disappears is who placed them: the
 * account identity, the address book, the identity frozen on the invoice and
 * delivery order addresses, the carts, the newsletter subscription and the
 * identity the administrator audit trail recorded about that customer.
 *
 * The country and state of an order address are kept, because they justify
 * the tax rate applied on the order.
 *
 * Modules add their own data to the operation by implementing
 * CustomerPersonalDataProviderInterface.
 */
final readonly class CustomerAnonymizer
{
    public const ANONYMIZED_VALUE = 'anonymous';
    public const ANONYMIZED_EMAIL_DOMAIN = 'anonymous.invalid';
    public const ANONYMIZED_ADMIN_LOG_MESSAGE = 'Personal data erased from this entry';

    /**
     * @param iterable<CustomerPersonalDataProviderInterface> $personalDataProviders
     */
    public function __construct(
        #[AutowireIterator('thelia.customer.personal_data_provider')]
        private iterable $personalDataProviders = [],
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function anonymize(Customer $customer): void
    {
        $connection = Propel::getConnection(CustomerTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->anonymizeOrderAddresses($customer, $connection);
            $this->deleteCarts($customer, $connection);
            $this->deleteAddresses($customer, $connection);
            $this->deleteNewsletterSubscription($customer, $connection);
            $this->anonymizeAccount($customer, $connection);
            $this->anonymizeAdminLogs($customer, $connection);

            foreach ($this->personalDataProviders as $provider) {
                $provider->anonymizePersonalData($customer);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * The order addresses are frozen copies made at checkout: they are not
     * shared with the address book, so they are neutralized in place rather
     * than deleted, which would break the orders that reference them.
     */
    private function anonymizeOrderAddresses(Customer $customer, ConnectionInterface $connection): void
    {
        $orderAddressIds = [];

        foreach (OrderQuery::create()->filterByCustomerId($customer->getId())->find($connection) as $order) {
            $orderAddressIds[] = $order->getInvoiceOrderAddressId();
            $orderAddressIds[] = $order->getDeliveryOrderAddressId();
        }

        $orderAddressIds = array_values(array_unique(array_filter($orderAddressIds)));

        if ([] === $orderAddressIds) {
            return;
        }

        $orderAddresses = OrderAddressQuery::create()
            ->filterById($orderAddressIds)
            ->find($connection);

        foreach ($orderAddresses as $orderAddress) {
            $orderAddress
                ->setCustomerTitleId(null)
                ->setCompany(null)
                // A registration number identifies the company as precisely as the name it
                // belongs to, so it is erased with it rather than left on the kept order.
                ->setSiret(null)
                ->setVatNumber(null)
                ->setFirstname(self::ANONYMIZED_VALUE)
                ->setLastname(self::ANONYMIZED_VALUE)
                ->setAddress1(self::ANONYMIZED_VALUE)
                ->setAddress2(null)
                ->setAddress3(null)
                ->setZipcode(self::ANONYMIZED_VALUE)
                ->setCity(self::ANONYMIZED_VALUE)
                ->setPhone(null)
                ->setCellphone(null)
                ->save($connection);
        }
    }

    /**
     * A cart holds a copy of the delivery and invoice addresses, so the cart
     * addresses are deleted along with the carts themselves.
     */
    private function deleteCarts(Customer $customer, ConnectionInterface $connection): void
    {
        $carts = CartQuery::create()->filterByCustomerId($customer->getId())->find($connection);

        $cartAddressIds = [];

        foreach ($carts as $cart) {
            $cartAddressIds[] = $cart->getAddressDeliveryId();
            $cartAddressIds[] = $cart->getAddressInvoiceId();
            $cart->delete($connection);
        }

        $cartAddressIds = array_values(array_unique(array_filter($cartAddressIds)));

        if ([] === $cartAddressIds) {
            return;
        }

        CartAddressQuery::create()->filterById($cartAddressIds)->delete($connection);
    }

    private function deleteAddresses(Customer $customer, ConnectionInterface $connection): void
    {
        $addressIds = AddressQuery::create()
            ->filterByCustomerId($customer->getId())
            ->select('Id')
            ->find($connection)
            ->toArray();

        if ([] === $addressIds) {
            return;
        }

        // A cart address keeps a reference to the address book entry it was
        // copied from, under a RESTRICT foreign key: clear it first.
        $cartAddresses = CartAddressQuery::create()
            ->filterByAddressId($addressIds)
            ->find($connection);

        foreach ($cartAddresses as $cartAddress) {
            $cartAddress->setAddressId(null)->save($connection);
        }

        AddressQuery::create()->filterById($addressIds)->delete($connection);
    }

    private function deleteNewsletterSubscription(Customer $customer, ConnectionInterface $connection): void
    {
        $email = $customer->getEmail();

        if (null === $email || '' === $email) {
            return;
        }

        NewsletterQuery::create()->filterByEmail($email)->delete($connection);
    }

    private function anonymizeAccount(Customer $customer, ConnectionInterface $connection): void
    {
        $customer
            ->setFirstname(self::ANONYMIZED_VALUE)
            ->setLastname(self::ANONYMIZED_VALUE)
            ->setEmail(\sprintf('%s-%d@%s', self::ANONYMIZED_VALUE, $customer->getId(), self::ANONYMIZED_EMAIL_DOMAIN))
            ->setAlgo(null)
            ->setSponsor(null)
            ->setRememberMeToken(null)
            ->setRememberMeSerial(null)
            ->setConfirmationToken(null)
            ->setConfirmationTokenExpiresAt(null)
            ->setEnable(0);

        // The marker is what tells an anonymous account from an account that
        // simply has no name yet. It records the first erasure: replaying the
        // operation must not move the date, or the retention job would lose
        // track of when the data actually went away.
        if (null === $customer->getAnonymizedAt()) {
            $customer->setAnonymizedAt(new \DateTime());
        }

        $customer->erasePassword();
        $customer->save($connection);

        // The versionable behavior keeps a full copy of every past revision of
        // the account, identity included. Anonymizing the row is pointless
        // while that history is still readable.
        CustomerVersionQuery::create()->filterById($customer->getId())->delete($connection);
    }

    /**
     * An ordinary back-office edit of a customer writes their identity twice
     * in the audit trail: in the message, which is composed from the name, and
     * in the serialized request, which holds the whole posted form
     * (AdminLog::append() with $withRequestContent). Neither survives an
     * erasure.
     *
     * What the trail is for does survive: admin_login, action and created_at
     * are kept, so it still says who did what, to which customer, and when.
     * Only the rows carrying this customer as their resource are rewritten,
     * so the trail of every other resource is left untouched. updated_at
     * records the rewrite itself, rather than letting the row change silently.
     */
    private function anonymizeAdminLogs(Customer $customer, ConnectionInterface $connection): void
    {
        AdminLogQuery::create()
            ->filterByResource(AdminResources::CUSTOMER)
            ->filterByResourceId($customer->getId())
            ->update(
                [
                    'Message' => self::ANONYMIZED_ADMIN_LOG_MESSAGE,
                    'Request' => null,
                    'UpdatedAt' => new \DateTime(),
                ],
                $connection,
            );
    }
}

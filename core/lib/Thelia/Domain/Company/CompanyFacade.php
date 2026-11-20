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

namespace Thelia\Domain\Company;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Company\DTO\CompanyData;
use Thelia\Domain\Company\Event\CompanyCustomerDetachedEvent;
use Thelia\Domain\Company\Event\CompanyCustomerEvent;
use Thelia\Domain\Company\Event\CompanyCustomerRoleChangedEvent;
use Thelia\Domain\Company\Event\CompanyEvent;
use Thelia\Domain\Company\Exception\AddressSharingRefusedException;
use Thelia\Domain\Company\Exception\CompanyHasOrdersException;
use Thelia\Domain\Company\Exception\CustomerAlreadyInCompanyException;
use Thelia\Domain\Company\Exception\CustomerNotInCompanyException;
use Thelia\Domain\Company\Exception\GuestCannotJoinCompanyException;
use Thelia\Domain\Company\Exception\InvalidCompanyDataException;
use Thelia\Domain\Company\Exception\InvalidCompanyIdentifiersException;
use Thelia\Domain\Company\Exception\LastCompanyAdministratorException;
use Thelia\Domain\Company\Exception\UnknownCompanyException;
use Thelia\Domain\Company\Exception\UnknownCompanyRoleException;
use Thelia\Domain\Company\Service\CompanyLimits;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Domain\Company\Service\CompanyRoleResolver;
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CompanyCustomerTableMap;
use Thelia\Model\Map\CompanyTableMap;
use Thelia\Model\OrderQuery;

/**
 * The one way a company, its buyers and its administrators change, whoever asks: the back
 * office, the API, a module or a command.
 *
 * Every operation is one transaction that starts by locking the row of the company by its
 * primary key, so that two administrators leaving at once cannot both be the one who was not the
 * last, and tells the shop with an event once the transaction is committed. What was read before
 * the lock is read again under it.
 *
 * A buyer who leaves hands the shared addresses they created to the first administrator who
 * stays, before the link is cut; the orders they placed keep the company they were placed for.
 */
final readonly class CompanyFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CompanyRoleResolver $roleResolver,
        private CompanyMembershipReader $membershipReader,
        private ?CompanyPermissionChecker $permissionChecker = null,
    ) {
    }

    /**
     * @throws InvalidCompanyDataException
     * @throws InvalidCompanyIdentifiersException
     */
    public function create(CompanyData $data): Company
    {
        $this->assertValid($data);
        $this->assertIdentifiersAcceptable($data, null);

        if (null !== $data->billingAddressId) {
            throw new InvalidCompanyDataException('A new company has no shared address yet: choose its billing address once it exists.');
        }

        $company = $this->inTransaction(function (ConnectionInterface $connection) use ($data): Company {
            $company = new Company();
            $this->fill($company, $data);
            $company->setActive(1);
            $company->save($connection);

            return $company;
        });

        $this->dispatcher->dispatch(new CompanyEvent($company), TheliaEvents::COMPANY_CREATE);

        return $company;
    }

    /**
     * Writes the details and copies the name, the SIRET and the VAT number onto the billing
     * address of the company, which is the one the invoices read them from.
     *
     * @throws InvalidCompanyDataException        when the billing address is not an address shared with this company
     * @throws InvalidCompanyIdentifiersException when the SIRET or the VAT number is refused for the country of the billing address
     * @throws UnknownCompanyException
     */
    public function update(Company $company, CompanyData $data): Company
    {
        $this->assertValid($data);

        $this->inTransaction(function (ConnectionInterface $connection) use ($company, $data): void {
            $this->lockCompany($company, $connection);

            if (null !== $data->billingAddressId
                && !AddressQuery::create()->filterById($data->billingAddressId)->filterByCompanyId((int) $company->getId())->exists($connection)) {
                throw new InvalidCompanyDataException('The billing address of a company must be an address shared with it.');
            }

            // A company saved before the rules were applied keeps what it holds until its
            // identifiers or its billing address change: it is not rewritten behind its back.
            if ((string) $company->getSiret() !== (string) $this->nullIfBlank($data->siret)
                || (string) $company->getVatNumber() !== (string) $this->nullIfBlank($data->vatNumber)
                || $company->getBillingAddressId() !== $data->billingAddressId) {
                $this->assertIdentifiersAcceptable($data, $connection);
            }

            $this->fill($company, $data);
            $company->save($connection);

            if (null !== $data->billingAddressId) {
                AddressQuery::create()->findPk($data->billingAddressId, $connection)
                    ?->setCompany($company->getName())
                    ->setSiret($company->getSiret())
                    ->setVatNumber($company->getVatNumber())
                    ->save($connection);
            }
        });

        $this->dispatcher->dispatch(new CompanyEvent($company), TheliaEvents::COMPANY_UPDATE);

        return $company;
    }

    /**
     * @param (callable(ConnectionInterface): void)|null $underTheLock run once the company is locked and before the link is written, in the same
     *                                                                 transaction: what it checks and what it records stand or fall with the link
     *
     * @throws GuestCannotJoinCompanyException
     * @throws CustomerAlreadyInCompanyException
     * @throws UnknownCompanyRoleException       when the role does not exist or belongs to another company
     * @throws InvalidCompanyDataException       when a limit or the period is not acceptable
     */
    public function attach(
        Company $company,
        Customer $customer,
        string $roleCode,
        ?string $orderLimit,
        ?string $periodLimit,
        ?string $period,
        ?callable $underTheLock = null,
    ): CompanyCustomer {
        $customerId = (int) $customer->getId();

        if ($customer->isNew() || 0 === $customerId) {
            throw new InvalidCompanyDataException('Only a stored account can join a company.');
        }

        if ($customer->isGuest()) {
            throw GuestCannotJoinCompanyException::withId($customerId);
        }

        CompanyLimits::assertValid($orderLimit, $periodLimit, $period);

        $buyer = $this->inTransaction(function (ConnectionInterface $connection) use ($company, $customerId, $roleCode, $orderLimit, $periodLimit, $period, $underTheLock): CompanyCustomer {
            $this->lockCompany($company, $connection);

            if (null !== $underTheLock) {
                $underTheLock($connection);
            }

            if (CompanyCustomerQuery::create()->filterByCustomerId($customerId)->exists($connection)) {
                throw CustomerAlreadyInCompanyException::withId($customerId);
            }

            $role = $this->roleResolver->resolve((int) $company->getId(), $roleCode, $connection)
                ?? throw UnknownCompanyRoleException::forCompany($roleCode, (int) $company->getId());

            $buyer = new CompanyCustomer();
            $buyer->setCompanyId((int) $company->getId());
            $buyer->setCustomerId($customerId);
            $buyer->setCompanyRoleId((int) $role->getId());
            $buyer->setOrderLimit($orderLimit);
            $buyer->setPeriodLimit($periodLimit);
            $buyer->setPeriod($period);
            $buyer->save($connection);

            return $buyer;
        });

        $this->membershipReader->forget($customerId);
        $this->dispatcher->dispatch(new CompanyCustomerEvent($buyer), TheliaEvents::COMPANY_CUSTOMER_ATTACH);

        return $buyer;
    }

    /**
     * Cuts the link between a customer and the company, once the shared addresses the customer
     * created belong to the first administrator who stays: the one with the smallest link.
     *
     * @throws CustomerNotInCompanyException
     * @throws LastCompanyAdministratorException when the customer is the last administrator, or
     *                                           holds shared addresses and no administrator stays to take them
     */
    public function detach(Customer $customer): void
    {
        $customerId = (int) $customer->getId();
        $companyId = $this->companyIdOf($customerId);

        $detached = $this->inTransaction(function (ConnectionInterface $connection) use ($customerId, $companyId): CompanyCustomerDetachedEvent {
            $this->lockCompanyById($companyId, $connection);

            $buyer = $this->buyerOf($customerId, $companyId, $connection);
            $role = $this->roleOf($buyer, $connection);
            $successor = $this->firstOtherAdministrator($companyId, $customerId, $connection);

            if (null === $successor && $this->roleResolver->isAdministrator($role)) {
                throw LastCompanyAdministratorException::leaving($companyId);
            }

            if (null === $successor && $this->sharedAddressIdsOf($companyId, $customerId, $connection) !== []) {
                throw LastCompanyAdministratorException::noOneToTakeTheAddresses($companyId);
            }

            $addressIds = null === $successor ? [] : $this->handOverAddresses($companyId, $customerId, $successor, $connection);

            $buyer->delete($connection);
            CompanyCustomerTableMap::clearInstancePool();

            return new CompanyCustomerDetachedEvent($companyId, $customerId, (string) $role->getCode(), $addressIds, [] === $addressIds ? null : $successor);
        });

        $this->membershipReader->forget($customerId);
        $this->dispatcher->dispatch($detached, TheliaEvents::COMPANY_CUSTOMER_DETACH);
    }

    /**
     * Cuts the link of an account that is being erased. Unlike {@see detach()} it never refuses:
     * the account goes whoever it is.
     *
     * The shared addresses it created go to the first administrator who stays. When it was the
     * last administrator, the buyer of the company who joined first and is still enabled becomes
     * administrator and takes them; with nobody to take over, the company is suspended and the
     * addresses are left to the erasure of the address book of the account.
     *
     * @throws CustomerNotInCompanyException
     */
    public function release(Customer $customer): void
    {
        $customerId = (int) $customer->getId();
        $companyId = $this->companyIdOf($customerId);

        /** @var array{CompanyCustomerDetachedEvent, CompanyCustomerRoleChangedEvent|null, Company|null} $result */
        $result = $this->inTransaction(function (ConnectionInterface $connection) use ($customerId, $companyId): array {
            $this->lockCompanyById($companyId, $connection);

            $buyer = $this->buyerOf($customerId, $companyId, $connection);
            $role = $this->roleOf($buyer, $connection);
            $successor = $this->firstOtherAdministrator($companyId, $customerId, $connection);
            $promotion = null;
            $suspended = null;

            if (null === $successor && $this->roleResolver->isAdministrator($role)) {
                $heir = $this->oldestEnabledBuyer($companyId, $customerId, $connection);

                if (null === $heir) {
                    $suspended = CompanyQuery::create()->findPk($companyId, $connection) ?? throw UnknownCompanyException::withId($companyId);
                    $suspended->setActive(0)->save($connection);
                } else {
                    $promotion = $this->makeAdministrator($heir, $companyId, $connection);
                    $successor = $heir;
                }
            }

            $addressIds = null === $successor ? [] : $this->handOverAddresses($companyId, $customerId, $successor, $connection);

            $buyer->delete($connection);
            CompanyCustomerTableMap::clearInstancePool();

            return [
                new CompanyCustomerDetachedEvent($companyId, $customerId, (string) $role->getCode(), $addressIds, [] === $addressIds ? null : $successor),
                $promotion,
                $suspended,
            ];
        });

        [$detached, $promotion, $suspended] = $result;

        $this->membershipReader->forget($customerId);

        if (null !== $promotion) {
            $this->membershipReader->forget((int) $promotion->getBuyer()->getCustomerId());
            $this->dispatcher->dispatch($promotion, TheliaEvents::COMPANY_CUSTOMER_CHANGE_ROLE);
        }

        if (null !== $suspended) {
            $this->membershipReader->reset();
            $this->dispatcher->dispatch(new CompanyEvent($suspended), TheliaEvents::COMPANY_SUSPEND);
        }

        $this->dispatcher->dispatch($detached, TheliaEvents::COMPANY_CUSTOMER_DETACH);
    }

    /**
     * @throws CustomerNotInCompanyException
     * @throws UnknownCompanyRoleException
     * @throws LastCompanyAdministratorException when the buyer is the last administrator and loses the role
     */
    public function changeRole(Customer $customer, string $roleCode): void
    {
        $customerId = (int) $customer->getId();
        $companyId = $this->companyIdOf($customerId);

        $event = $this->inTransaction(function (ConnectionInterface $connection) use ($customerId, $companyId, $roleCode): ?CompanyCustomerRoleChangedEvent {
            $this->lockCompanyById($companyId, $connection);

            $buyer = $this->buyerOf($customerId, $companyId, $connection);
            $previous = $this->roleOf($buyer, $connection);
            $new = $this->roleResolver->resolve($companyId, $roleCode, $connection)
                ?? throw UnknownCompanyRoleException::forCompany($roleCode, $companyId);

            if ($new->getId() === $previous->getId()) {
                return null;
            }

            if ($this->roleResolver->isAdministrator($previous)
                && !$this->roleResolver->isAdministrator($new)
                && null === $this->firstOtherAdministrator($companyId, $customerId, $connection)) {
                throw LastCompanyAdministratorException::leaving($companyId);
            }

            $buyer->setCompanyRoleId((int) $new->getId());
            $buyer->save($connection);

            return new CompanyCustomerRoleChangedEvent($buyer, (string) $previous->getCode(), (string) $new->getCode());
        });

        $this->membershipReader->forget($customerId);

        if (null !== $event) {
            $this->dispatcher->dispatch($event, TheliaEvents::COMPANY_CUSTOMER_CHANGE_ROLE);
        }
    }

    /**
     * @throws CustomerNotInCompanyException
     * @throws InvalidCompanyDataException   when a limit or the period is not acceptable
     */
    public function changeLimits(Customer $customer, ?string $orderLimit, ?string $periodLimit, ?string $period): void
    {
        CompanyLimits::assertValid($orderLimit, $periodLimit, $period);

        $customerId = (int) $customer->getId();
        $companyId = $this->companyIdOf($customerId);

        $buyer = $this->inTransaction(function (ConnectionInterface $connection) use ($customerId, $companyId, $orderLimit, $periodLimit, $period): CompanyCustomer {
            $this->lockCompanyById($companyId, $connection);

            $buyer = $this->buyerOf($customerId, $companyId, $connection);
            $buyer->setOrderLimit($orderLimit);
            $buyer->setPeriodLimit($periodLimit);
            $buyer->setPeriod($period);
            $buyer->save($connection);

            return $buyer;
        });

        $this->membershipReader->forget($customerId);
        $this->dispatcher->dispatch(new CompanyCustomerEvent($buyer), TheliaEvents::COMPANY_CUSTOMER_CHANGE_LIMITS);
    }

    /**
     * Shares an address with the company of the customer, or takes the sharing back.
     *
     * Any buyer shares an address they created. The sharing is taken back by whoever may change
     * a shared address: its creator, or a buyer whose role manages the shared ones. The address
     * a company invoices stays shared as long as the company invoices it. Asking for what is
     * already so changes nothing.
     *
     * @throws AddressSharingRefusedException
     * @throws UnknownCompanyException
     */
    public function shareAddress(Customer $actor, Address $address, bool $shared): void
    {
        $actorId = (int) $actor->getId();
        $addressId = (int) $address->getId();
        $membership = $this->membershipReader->membershipOf($actorId);

        if ($shared && null === $membership) {
            throw AddressSharingRefusedException::notAMember($actorId);
        }

        $companyId = $shared ? (int) $membership->companyId : (int) $address->getCompanyId();

        if (!$shared && 0 === $companyId) {
            return;
        }

        $this->inTransaction(function (ConnectionInterface $connection) use ($actor, $actorId, $addressId, $companyId, $shared): void {
            $this->lockCompanyById($companyId, $connection);

            $stored = AddressQuery::create()->findPk($addressId, $connection);

            if (!$stored instanceof Address) {
                throw AddressSharingRefusedException::notTheCreator($actorId, $addressId);
            }

            if ($shared) {
                if ((int) $stored->getCustomerId() !== $actorId) {
                    throw AddressSharingRefusedException::notTheCreator($actorId, $addressId);
                }

                if ($companyId !== (int) $stored->getCompanyId()) {
                    $stored->setCompanyId($companyId)->save($connection);
                }

                return;
            }

            if (null === $stored->getCompanyId()) {
                return;
            }

            if (!$this->permissionChecker()->canEditAddress($actor, $stored)) {
                throw AddressSharingRefusedException::notAllowedToChange($actorId, $addressId);
            }

            if (CompanyQuery::create()->filterById($companyId)->filterByBillingAddressId($addressId)->exists($connection)) {
                throw AddressSharingRefusedException::isTheBillingAddress($addressId);
            }

            $stored->setCompanyId(null)->save($connection);
        });

        AddressTableMap::clearInstancePool();
    }

    public function suspend(Company $company): void
    {
        $this->setActive($company, 0, TheliaEvents::COMPANY_SUSPEND);
    }

    public function reactivate(Company $company): void
    {
        $this->setActive($company, 1, TheliaEvents::COMPANY_REACTIVATE);
    }

    /**
     * Deletes a company that no order was placed for: its buyers are released, its roles go
     * with it, and the addresses it shared become the personal ones of their creators.
     *
     * @throws CompanyHasOrdersException a company with history is suspended, not deleted
     */
    public function delete(Company $company): void
    {
        $this->inTransaction(function (ConnectionInterface $connection) use ($company): void {
            $this->lockCompany($company, $connection);

            if (OrderQuery::create()->filterByCompanyId((int) $company->getId())->exists($connection)) {
                throw CompanyHasOrdersException::forCompany((int) $company->getId());
            }

            $company->delete($connection);
            $this->forgetPooledRows();
        });

        $this->membershipReader->reset();
        $this->dispatcher->dispatch(new CompanyEvent($company), TheliaEvents::COMPANY_DELETE);
    }

    private function setActive(Company $company, int $active, string $eventName): void
    {
        $changed = $this->inTransaction(function (ConnectionInterface $connection) use ($company, $active): bool {
            $this->lockCompany($company, $connection);

            if ($active === (int) $company->getActive()) {
                return false;
            }

            $company->setActive($active);
            $company->save($connection);

            return true;
        });

        if ($changed) {
            $this->membershipReader->reset();
            $this->dispatcher->dispatch(new CompanyEvent($company), $eventName);
        }
    }

    /**
     * @template T
     *
     * @param callable(ConnectionInterface): T $work
     *
     * @return T
     */
    private function inTransaction(callable $work): mixed
    {
        $connection = Propel::getConnection(CompanyTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $result = $work($connection);
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    private function lockCompany(Company $company, ConnectionInterface $connection): void
    {
        $this->lockCompanyById((int) $company->getId(), $connection);

        // What the caller holds may be older than the lock: it is read again, from the row.
        $company->reload(false, $connection);
    }

    /**
     * The first statement of a transaction: the row of the company, locked by its primary key,
     * before anything else is read; what the pool kept from before the lock is dropped.
     */
    private function lockCompanyById(int $companyId, ConnectionInterface $connection): void
    {
        $this->forgetPooledRows();

        if (0 === $companyId || !CompanyQuery::create()->filterById($companyId)->lockForUpdate()->findOne($connection) instanceof Company) {
            throw UnknownCompanyException::withId($companyId);
        }
    }

    private function permissionChecker(): CompanyPermissionChecker
    {
        return $this->permissionChecker ?? new CompanyPermissionChecker($this->membershipReader);
    }

    private function forgetPooledRows(): void
    {
        CompanyTableMap::clearInstancePool();
        CompanyCustomerTableMap::clearInstancePool();
        AddressTableMap::clearInstancePool();
    }

    private function companyIdOf(int $customerId): int
    {
        /** @var int|string|null $companyId */
        $companyId = CompanyCustomerQuery::create()
            ->filterByCustomerId($customerId)
            ->select('CompanyId')
            ->findOne();

        return null === $companyId ? throw CustomerNotInCompanyException::withId($customerId) : (int) $companyId;
    }

    private function buyerOf(int $customerId, int $companyId, ConnectionInterface $connection): CompanyCustomer
    {
        $buyer = CompanyCustomerQuery::create()->filterByCustomerId($customerId)->findOne($connection);

        if (!$buyer instanceof CompanyCustomer || (int) $buyer->getCompanyId() !== $companyId) {
            throw CustomerNotInCompanyException::withId($customerId);
        }

        return $buyer;
    }

    private function roleOf(CompanyCustomer $buyer, ConnectionInterface $connection): CompanyRole
    {
        return CompanyRoleQuery::create()->findPk($buyer->getCompanyRoleId(), $connection)
            ?? throw new \LogicException(\sprintf('The role %d of the buyer %d is gone.', $buyer->getCompanyRoleId(), $buyer->getCustomerId()));
    }

    /**
     * The customer who takes over from one who leaves: the first administrator still enabled,
     * in the order they joined.
     */
    private function firstOtherAdministrator(int $companyId, int $leavingCustomerId, ConnectionInterface $connection): ?int
    {
        /** @var int|string|null $customerId */
        $customerId = CompanyCustomerQuery::create()
            ->filterByCompanyId($companyId)
            ->filterByCustomerId($leavingCustomerId, Criteria::NOT_EQUAL)
            ->useCompanyRoleQuery()
                ->filterByCode(CompanyRoleCode::ADMINISTRATOR)
                ->filterByCompanyId(null, Criteria::ISNULL)
            ->endUse()
            ->useCustomerQuery()
                ->filterByEnable(1)
            ->endUse()
            ->orderById()
            ->select('CustomerId')
            ->findOne($connection);

        return null === $customerId ? null : (int) $customerId;
    }

    /**
     * @return list<int>
     */
    private function sharedAddressIdsOf(int $companyId, int $customerId, ConnectionInterface $connection): array
    {
        return array_map(
            'intval',
            AddressQuery::create()
                ->filterByCompanyId($companyId)
                ->filterByCustomerId($customerId)
                ->select('Id')
                ->find($connection)
                ->getData(),
        );
    }

    /**
     * @return list<int> the shared addresses handed over
     */
    private function handOverAddresses(int $companyId, int $fromCustomerId, int $toCustomerId, ConnectionInterface $connection): array
    {
        $addressIds = $this->sharedAddressIdsOf($companyId, $fromCustomerId, $connection);

        if ([] !== $addressIds) {
            // A default address is the default of one customer: the one handed over is not
            // the default of the administrator who takes it.
            AddressQuery::create()
                ->filterById($addressIds, Criteria::IN)
                ->update(['CustomerId' => $toCustomerId, 'IsDefault' => 0], $connection);
            AddressTableMap::clearInstancePool();
        }

        return $addressIds;
    }

    /**
     * The buyer who joined first and is still an enabled account, the leaving one aside.
     */
    private function oldestEnabledBuyer(int $companyId, int $leavingCustomerId, ConnectionInterface $connection): ?int
    {
        /** @var int|string|null $customerId */
        $customerId = CompanyCustomerQuery::create()
            ->filterByCompanyId($companyId)
            ->filterByCustomerId($leavingCustomerId, Criteria::NOT_EQUAL)
            ->useCustomerQuery()
                ->filterByEnable(1)
                ->filterByIsGuest(0)
            ->endUse()
            ->orderByCreatedAt()
            ->orderById()
            ->select('CustomerId')
            ->findOne($connection);

        return null === $customerId ? null : (int) $customerId;
    }

    private function makeAdministrator(int $customerId, int $companyId, ConnectionInterface $connection): CompanyCustomerRoleChangedEvent
    {
        $buyer = $this->buyerOf($customerId, $companyId, $connection);
        $previous = $this->roleOf($buyer, $connection);
        $administrator = $this->roleResolver->resolve($companyId, CompanyRoleCode::ADMINISTRATOR, $connection)
            ?? throw UnknownCompanyRoleException::forCompany(CompanyRoleCode::ADMINISTRATOR, $companyId);

        $buyer->setCompanyRoleId((int) $administrator->getId());
        $buyer->save($connection);

        return new CompanyCustomerRoleChangedEvent($buyer, (string) $previous->getCode(), (string) $administrator->getCode());
    }

    private function fill(Company $company, CompanyData $data): void
    {
        $company->setName(trim($data->name));
        $company->setSiret($this->nullIfBlank($data->siret));
        $company->setVatNumber($this->nullIfBlank($data->vatNumber));
        $company->setParentId($data->parentId);
        $company->setNotificationEmail($this->nullIfBlank($data->notificationEmail));
        $company->setInternalNote($this->nullIfBlank($data->internalNote));
        $company->setBillingAddressId($data->billingAddressId);
    }

    private function nullIfBlank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }

    /**
     * The identifiers are judged by the rules an address is judged by, for the country of the
     * billing address they are copied onto. Without a billing address there is no country, and
     * they are judged as an address without one is.
     *
     * @throws InvalidCompanyIdentifiersException
     */
    private function assertIdentifiersAcceptable(CompanyData $data, ?ConnectionInterface $connection): void
    {
        $countryCode = null === $data->billingAddressId
            ? null
            : AddressQuery::create()->findPk($data->billingAddressId, $connection)?->getCountry()?->getIsoalpha2();

        $violations = CompanyIdentifierRules::violationsFor(
            trim($data->name),
            $this->nullIfBlank($data->siret),
            $this->nullIfBlank($data->vatNumber),
            $countryCode,
        );

        if ([] !== $violations) {
            throw new InvalidCompanyIdentifiersException($violations);
        }
    }

    private function assertValid(CompanyData $data): void
    {
        if ('' === trim($data->name) || mb_strlen(trim($data->name)) > 255) {
            throw new InvalidCompanyDataException('A company needs a name of 255 characters at most.');
        }

        foreach (['SIRET' => $data->siret, 'VAT number' => $data->vatNumber] as $label => $value) {
            if (null !== $value && mb_strlen(trim($value)) > 20) {
                throw new InvalidCompanyDataException(\sprintf('The %s of a company holds 20 characters at most.', $label));
            }
        }

        $email = $this->nullIfBlank($data->notificationEmail);

        if (null !== $email && (mb_strlen($email) > 255 || false === filter_var($email, \FILTER_VALIDATE_EMAIL))) {
            throw new InvalidCompanyDataException('The notification e-mail address of a company is not valid.');
        }

        if (null !== $data->parentId && $data->parentId < 1) {
            throw new InvalidCompanyDataException('The parent of a company is a company number.');
        }
    }
}

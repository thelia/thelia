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

namespace Thelia\Domain\Payment;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerPaymentMethodEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Exception\GuestCannotStorePaymentMethodException;
use Thelia\Domain\Payment\Exception\PaymentMethodNotOwnedException;
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;
use Thelia\Domain\Payment\Exception\StoredMethodsNotSupportedException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\DeferredStoredMethodForgettingPaymentModuleInterface;
use Thelia\Module\ModuleTransactionGuard;
use Thelia\Module\PaymentModuleWithStoredMethodsInterface;

/**
 * The payment methods a customer asked the shop to keep.
 *
 * A customer has at most one default method, and has one as soon as they have any. Every
 * write locks the customer row first: two registrations of the same customer, the return
 * URL and the provider notification of one payment for instance, are serialized there
 * instead of both inserting the same token or both setting a default. The customer row
 * exists, so the lock is a record lock and never a gap lock on the methods of the
 * neighbouring customers. No provider is called while that lock is held, except by the
 * removal of a module (see forgetAllOfModule()): a provider call can take seconds, and
 * the lock would hold every write on the customer back.
 *
 * The token is handed to the payment module and to nothing else: no message, no log
 * line and no exception written here carries it.
 */
final readonly class CustomerPaymentMethodService
{
    // The size of the `label` column, in characters.
    public const LABEL_MAXIMUM_LENGTH = 255;

    public function __construct(
        private ContainerInterface $container,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Records the method a payment module obtained from its provider.
     *
     * Registering a token the customer already stored with this module refreshes its
     * label and its expiry rather than adding a second row, so a module may register on
     * every payment. The first method of a customer becomes the default.
     *
     * Call it outside any transaction. Called inside one the caller opened, it reads
     * under a lock and clears the default by an update filtered on the customer, which
     * both lock the gap of the customer_id index: two first registrations of
     * neighbouring customers may then deadlock (1213).
     *
     * @throws GuestCannotStorePaymentMethodException
     * @throws StoredMethodsNotSupportedException
     * @throws \InvalidArgumentException              when the token or the label is empty, or the label
     *                                                longer than LABEL_MAXIMUM_LENGTH
     */
    public function register(
        Customer $customer,
        Module $module,
        string $token,
        string $label,
        ?\DateTimeInterface $expiresAt,
        bool $default = false,
    ): CustomerPaymentMethod {
        if ($customer->isGuest()) {
            throw new GuestCannotStorePaymentMethodException();
        }

        if (!$this->offersStoredMethods($module)) {
            throw new StoredMethodsNotSupportedException((string) $module->getCode());
        }

        if ('' === $token) {
            throw new \InvalidArgumentException('A stored payment method needs the reference its provider handed back.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException('A stored payment method needs a label the customer can recognize it by.');
        }

        if (mb_strlen($label) > self::LABEL_MAXIMUM_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('The label of a stored payment method holds %d characters at most.', self::LABEL_MAXIMUM_LENGTH));
        }

        $connection = $this->connection();
        $readsSeeCommits = !$connection->inTransaction();
        $connection->beginTransaction();

        try {
            $this->lockTheCustomer((int) $customer->getId(), $connection);

            $method = $this->alreadyStored($customer, $module, $token, $readsSeeCommits, $connection);
            $isNew = null === $method;

            if (null === $method) {
                $method = (new CustomerPaymentMethod())
                    ->setCustomerId($customer->getId())
                    ->setPaymentModuleId($module->getId())
                    ->setToken($token)
                    ->setIsDefault(false);
            }

            $method->setLabel($label)->setExpiresAt($expiresAt);

            if ($default || ($isNew && !$this->hasAny($customer, $readsSeeCommits, $connection))) {
                $this->clearTheDefault($customer, $method, $readsSeeCommits, $connection);
                $method->setIsDefault(true);
            }

            $method->save($connection);
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        if ($isNew) {
            $this->dispatcher->dispatch(new CustomerPaymentMethodEvent($method), TheliaEvents::CUSTOMER_PAYMENT_METHOD_CREATE);
        }

        return $method;
    }

    /**
     * Returns the row as written, read again under the lock.
     *
     * Call it outside any transaction: called inside one the caller opened, the default
     * is cleared by an update filtered on the customer, which locks the gap of the
     * customer_id index, and may deadlock with a first registration of a neighbouring
     * customer (1213).
     *
     * @throws PaymentMethodNotOwnedException
     */
    public function setDefault(Customer $customer, CustomerPaymentMethod $method): CustomerPaymentMethod
    {
        $connection = $this->connection();
        $readsSeeCommits = !$connection->inTransaction();
        $connection->beginTransaction();

        try {
            $this->lockTheCustomer((int) $customer->getId(), $connection);

            $owned = $this->ownedOrRefuse($customer, $method, $readsSeeCommits, $connection);

            $this->clearTheDefault($customer, $owned, $readsSeeCommits, $connection);
            $owned->setIsDefault(true)->save($connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return $owned;
    }

    /**
     * Asks the provider to forget the method, then drops it.
     *
     * The provider is asked first, outside the transaction: when it refuses, or fails,
     * the exception reaches the caller and nothing is dropped. The row is then dropped
     * under the lock; should that fail, the provider has already forgotten a method the
     * shop still lists, which the customer can only remove again. A module that can no
     * longer be asked, its class gone or the interface no longer implemented, does not
     * hold the removal back: keeping the method would leave the customer one nobody can
     * use nor remove. A module that implements the interface is asked even when it no
     * longer offers new stored methods. When the dropped method was the default, the most
     * recent remaining one takes its place.
     *
     * Call it outside any transaction: called inside one the caller opened, the rows are
     * read under a lock, gap of the customer_id index included, and a registration of a
     * neighbouring customer waits for the caller's commit. The first read then locks the
     * method before its customer, the reverse of every other write here: against a
     * setDefault() or a register() as the default of the same customer, either may be
     * refused with a deadlock (1213). That lock is held while the provider is asked, so
     * such a setDefault() or register() may also run out of lock wait time (1205).
     *
     * @throws PaymentMethodNotOwnedException
     * @throws StoredMethodRefusedException   when the provider refuses, or fails in any way: what it
     *                                        threw is never passed on, its message may quote the token
     */
    public function forget(Customer $customer, CustomerPaymentMethod $method): void
    {
        $connection = $this->connection();
        $readsSeeCommits = !$connection->inTransaction();
        // A first look, before the provider is asked: the row is read again under the lock.
        $owned = $this->ownedOrRefuse($customer, $method, $readsSeeCommits, $connection);
        $module = ModuleQuery::create()->findPk($owned->getPaymentModuleId(), $connection);
        $moduleInstance = null === $module ? null : $this->storedMethodsModule($module);

        if (null === $moduleInstance) {
            $this->logger->warning(\sprintf(
                'Stored payment method #%d dropped without telling its provider: module #%d can no longer be asked.',
                (int) $owned->getId(),
                (int) $owned->getPaymentModuleId(),
            ));
        }

        try {
            $moduleInstance?->forgetStoredMethod($owned);
        } catch (StoredMethodRefusedException $refusal) {
            // By the length of what the provider said only: it may quote the token.
            $this->logger->warning(\sprintf(
                'Stored payment method #%d kept: its provider refused to forget it (module #%d, reason of %d characters withheld).',
                (int) $owned->getId(),
                (int) $owned->getPaymentModuleId(),
                mb_strlen((string) $refusal->providerReason),
            ));

            throw $refusal;
        } catch (\Throwable $failure) {
            // A provider SDK fails with its own exceptions (transport, decoding), whose
            // message quotes what it was handed, the token included. Left to go up, it
            // would end as a 500 whose message the error listener logs. It is a refusal
            // all the same: the provider did not forget the method, so the shop keeps it,
            // and the class alone is written down. An \Error is a bug of the module, not a
            // provider answer: it is logged as an error, with where it was thrown.
            if ($failure instanceof \Error) {
                $this->logger->error(\sprintf(
                    'Stored payment method #%d kept: the module failed with a bug while forgetting it (module #%d, %s at %s:%d, message withheld).',
                    (int) $owned->getId(),
                    (int) $owned->getPaymentModuleId(),
                    $failure::class,
                    $failure->getFile(),
                    $failure->getLine(),
                ));
            } else {
                $this->logger->warning(\sprintf(
                    'Stored payment method #%d kept: its provider failed while forgetting it (module #%d, %s, message withheld).',
                    (int) $owned->getId(),
                    (int) $owned->getPaymentModuleId(),
                    $failure::class,
                ));
            }

            throw new StoredMethodRefusedException();
        }

        $connection->beginTransaction();

        try {
            $this->lockTheCustomer((int) $customer->getId(), $connection);

            $owned = $this->ownedOrRefuse($customer, $owned, $readsSeeCommits, $connection);
            $wasDefault = true === $owned->getIsDefault();
            $owned->delete($connection);

            if ($wasDefault) {
                $this->promoteTheMostRecent((int) $customer->getId(), $readsSeeCommits, $connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->dispatcher->dispatch(new CustomerPaymentMethodEvent($owned), TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE);
    }

    /**
     * Whether the module may keep a new stored method right now: the one rule the
     * registration and the checkout listing both apply.
     */
    public function offersStoredMethods(Module $module): bool
    {
        return $this->storedMethodsModule($module)?->supportsStoredMethods() ?? false;
    }

    /**
     * The default method first, then the most recent; only those stored through
     * $throughModule when one is given.
     *
     * @return list<CustomerPaymentMethod>
     */
    public function listFor(Customer $customer, ?Module $throughModule = null): array
    {
        $query = CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId());

        if (null !== $throughModule) {
            $query->filterByPaymentModuleId($throughModule->getId());
        }

        return array_values(
            $query
                ->orderByIsDefault(Criteria::DESC)
                ->orderByCreatedAt(Criteria::DESC)
                ->orderById(Criteria::DESC)
                ->find()
                ->getData(),
        );
    }

    /**
     * The method of this id when it belongs to this customer, null otherwise: another
     * customer's method and a missing one read the same.
     */
    public function findOwned(Customer $customer, int $id): ?CustomerPaymentMethod
    {
        return CustomerPaymentMethodQuery::create()
            ->filterById($id)
            ->filterByCustomerId($customer->getId())
            ->findOne();
    }

    /**
     * Drops every method of the customer, for the anonymization.
     *
     * The rows go in the caller's transaction, whatever the providers answer: the
     * erasure of personal data does not wait on a third party. The providers are told
     * afterwards, through the returned callbacks, which the caller runs once its
     * transaction is committed. Each one asks the provider in best effort, logs a
     * failure without the token, and announces the deletion. A module that needs data
     * going with the row reads it before the drop
     * (DeferredStoredMethodForgettingPaymentModuleInterface).
     *
     * @return list<\Closure(): void>
     *
     * @throws \LogicException when the caller holds no transaction, or a module ended it
     */
    public function forgetAllOf(Customer $customer, ConnectionInterface $connection): array
    {
        if (!$connection->inTransaction()) {
            throw new \LogicException('Stored payment methods are dropped in the transaction of the anonymization: open it first.');
        }

        // Held until the caller commits: a registration arriving from here on, a payment
        // return for instance, waits for the erasure to end.
        $this->lockTheCustomer((int) $customer->getId(), $connection);

        // A registration committed before the lock was granted is not in the read view
        // the caller's transaction may already have taken, the anonymization's own reads
        // of the orders for instance: a plain read would miss it while a delete by
        // customer dropped it, its provider never told. The methods are read under a
        // lock, which reads them as they are, and only those are dropped. That read
        // also locks the gap of the customer_id index after them: a registration of a
        // neighbouring customer whose first method falls there waits for the commit.
        // It cannot deadlock with this transaction, which inserts no method and asks
        // for no lock that registration holds, its own customer row.
        $methods = CustomerPaymentMethodQuery::create()
            ->filterByCustomerId($customer->getId())
            ->orderById()
            ->lockForUpdate()
            ->find($connection)
            ->getData();

        if ([] === $methods) {
            return [];
        }

        // Before the drop: what a provider needs to forget a method may go with its row,
        // through a foreign key on delete cascade, and be gone once the caller commits.
        $preparedCalls = array_map(
            fn (CustomerPaymentMethod $method): ?\Closure => $this->prepareTheProviderToForget($method, $connection),
            $methods,
        );

        $this->dropByPrimaryKey($methods, $connection);

        return array_map(
            fn (CustomerPaymentMethod $method, ?\Closure $preparedCall): \Closure => function () use ($method, $preparedCall): void {
                $this->tellTheProviderToForget($method, preparedCall: $preparedCall);
                $this->dispatcher->dispatch(new CustomerPaymentMethodEvent($method), TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE);
            },
            $methods,
            $preparedCalls,
        );
    }

    /**
     * Drops every method stored through this module, before the module is removed:
     * the foreign key forbids removing a module a method still names.
     *
     * The provider is told first, while the module's classes are still on disk, in
     * best effort: a module on its way out cannot hold the removal back. A customer who
     * loses their default method this way and keeps others through another module gets
     * the most recent of those as their default. No event is dispatched; MODULE_DELETE
     * already says it.
     *
     * The providers are called inside the removal's transaction, which already holds the
     * orders of the module and, by then, the customers whose methods go: acceptable for
     * an administration task this rare, and the one exception to the rule above that no
     * provider is called under a customer lock. Each call runs under the transaction guard
     * of the module lifecycle, which puts the connection back in line. A provider that
     * rolls back, or runs a schema change, still loses what the transaction held, its
     * writes and locks: the removal then fails with a LogicException, and its caller rolls
     * back.
     *
     * @throws \LogicException when the caller holds no transaction
     */
    public function forgetAllOfModule(Module $module, ConnectionInterface $connection): void
    {
        if (!$connection->inTransaction()) {
            throw new \LogicException('Stored payment methods are dropped in the transaction of the module removal: open it first.');
        }

        // The customers are locked before the methods are read under a lock, which also
        // locks the gap of the payment_module_id index after them: a registration of one
        // of the customers read here, which holds its customer and then writes its
        // method, is not left waiting on that gap while this removal waits on its customer.
        $lockedCustomerIds = $this->lockTheCustomers(
            CustomerPaymentMethodQuery::create()
                ->filterByPaymentModuleId($module->getId())
                ->select('CustomerId')
                ->find($connection)
                ->toArray(),
            [],
            $connection,
        );

        // Read under a lock, as they are: the removal has read before, freezing the module
        // on the orders, and a method registered since is not in its read view. A plain
        // read would miss it while a delete by module dropped it, its provider never told.
        $methods = CustomerPaymentMethodQuery::create()
            ->filterByPaymentModuleId($module->getId())
            ->orderById()
            ->lockForUpdate()
            ->find($connection)
            ->getData();

        if ([] === $methods) {
            return;
        }

        // The owners of the methods registered in between, which the first read missed.
        // They are locked after the others and after the gap, out of the ascending order:
        // should one of them be registering a method at that very moment, the database may
        // break the wait with a deadlock (1213). The removal is then rolled back before any
        // provider was called, and can simply be run again.
        $this->lockTheCustomers(
            array_map(static fn (CustomerPaymentMethod $method): int => (int) $method->getCustomerId(), $methods),
            $lockedCustomerIds,
            $connection,
        );

        $customersLosingTheirDefault = [];

        foreach ($methods as $method) {
            $this->tellTheProviderToForget($method, $module, $connection);

            if (true === $method->getIsDefault()) {
                $customersLosingTheirDefault[] = (int) $method->getCustomerId();
            }
        }

        $this->dropByPrimaryKey($methods, $connection);

        // The removal has read before, so the methods left to promote are read under a
        // lock too: a plain read would miss one registered through another module since,
        // or hand back one forgotten since.
        foreach ($customersLosingTheirDefault as $customerId) {
            $this->promoteTheMostRecent($customerId, false, $connection);
        }
    }

    /**
     * The module row resolves to the instance the container built when the module was
     * active at compile time, the one Action\Module::pay() calls. A module that is not
     * in the container, deactivated on its way to removal or recorded after the
     * compilation, is instantiated from its namespace and given the container, which is
     * what the container definition does too (see TheliaKernel::loadModulesConfiguration()).
     */
    private function storedMethodsModule(Module $module): ?PaymentModuleWithStoredMethodsInterface
    {
        $serviceId = 'module.'.$module->getCode();

        if ($this->container->has($serviceId)) {
            $instance = $module->getModuleInstance($this->container);

            return $instance instanceof PaymentModuleWithStoredMethodsInterface ? $instance : null;
        }

        $moduleClass = $module->getFullNamespace();

        if (null === $moduleClass || !class_exists($moduleClass)) {
            return null;
        }

        $instance = $module->createInstance();
        $instance->setContainer($this->container);

        return $instance instanceof PaymentModuleWithStoredMethodsInterface ? $instance : null;
    }

    /**
     * Lets a module that needs data going with the row of a method read it before the
     * drop (DeferredStoredMethodForgettingPaymentModuleInterface), in the caller's
     * transaction and under the transaction guard. Best effort: a failure is logged by
     * its class only, and the provider is then told the plain way.
     *
     * @return (\Closure(): void)|null the call to make once the drop is committed, null
     *                                 for the plain forgetStoredMethod()
     *
     * @throws \LogicException when the module ended the caller's transaction
     */
    private function prepareTheProviderToForget(CustomerPaymentMethod $method, ConnectionInterface $connection): ?\Closure
    {
        $transactionLost = false;
        $module = null;

        try {
            $module = ModuleQuery::create()->findPk($method->getPaymentModuleId(), $connection);
            $moduleInstance = null === $module ? null : $this->storedMethodsModule($module);

            if (!$moduleInstance instanceof DeferredStoredMethodForgettingPaymentModuleInterface) {
                return null;
            }

            $levelOnEntry = $connection instanceof ConnectionWrapper ? $connection->getNestedTransactionCount() : null;

            return ModuleTransactionGuard::run(
                $connection,
                (string) $module->getCode(),
                'prepareToForgetStoredMethod',
                static function () use ($moduleInstance, $method, $connection, $levelOnEntry, &$transactionLost): \Closure {
                    try {
                        return $moduleInstance->prepareToForgetStoredMethod($method);
                    } finally {
                        $transactionLost = !$connection->inTransaction()
                            || (null !== $levelOnEntry && $connection->getNestedTransactionCount() < $levelOnEntry);
                    }
                },
            );
        } catch (\Throwable $throwable) {
            $this->logger->warning(\sprintf(
                'The provider of stored payment method #%d could not read what it needs to forget it before its drop (module #%d, %s).',
                (int) $method->getId(),
                (int) $method->getPaymentModuleId(),
                $throwable::class,
            ));

            return null;
        } finally {
            // A transaction the module ended cannot be given back with what it held: the
            // erasure must fail and its caller roll back.
            if ($transactionLost) {
                throw new \LogicException(\sprintf('The provider of stored payment method #%d (module %s) ended the transaction that drops it: it is aborted.', (int) $method->getId(), (string) $module?->getCode()));
            }
        }
    }

    /**
     * Best effort, for a method whose row is gone or about to go. Any failure is logged
     * by its class only: a provider message, and the trace arguments, may carry the
     * token.
     *
     * With a connection, the call is made inside the caller's transaction and runs under
     * the transaction guard; without one, nothing is open and the module is called as is.
     * A prepared call, made by the module before the drop, is run in place of
     * forgetStoredMethod().
     *
     * @param (\Closure(): void)|null $preparedCall
     *
     * @throws \LogicException when the provider ended the caller's transaction
     */
    private function tellTheProviderToForget(CustomerPaymentMethod $method, ?Module $module = null, ?ConnectionInterface $connection = null, ?\Closure $preparedCall = null): void
    {
        $transactionLost = false;

        try {
            if (null !== $preparedCall) {
                $preparedCall();

                return;
            }

            $module ??= ModuleQuery::create()->findPk($method->getPaymentModuleId());
            $moduleInstance = null === $module ? null : $this->storedMethodsModule($module);

            if (null === $moduleInstance) {
                $this->logger->warning(\sprintf(
                    'The provider of stored payment method #%d was not told to forget it: module #%d can no longer be asked.',
                    (int) $method->getId(),
                    (int) $method->getPaymentModuleId(),
                ));

                return;
            }

            if (null === $connection) {
                $moduleInstance->forgetStoredMethod($method);

                return;
            }

            $levelOnEntry = $connection instanceof ConnectionWrapper ? $connection->getNestedTransactionCount() : null;

            ModuleTransactionGuard::run(
                $connection,
                (string) $module->getCode(),
                'forgetStoredMethod',
                static function () use ($moduleInstance, $method, $connection, $levelOnEntry, &$transactionLost): void {
                    try {
                        $moduleInstance->forgetStoredMethod($method);
                    } finally {
                        // Read before the guard puts the level back: it does so silently, and
                        // what the transaction held (writes, locks) is gone with the rollback.
                        $transactionLost = !$connection->inTransaction()
                            || (null !== $levelOnEntry && $connection->getNestedTransactionCount() < $levelOnEntry);
                    }
                },
            );
        } catch (\Throwable $throwable) {
            // Third-party code, run where nothing can be rolled back any more: whatever it
            // throws is reported, never allowed to undo what the caller already did.
            $this->logger->warning(\sprintf(
                'The provider of stored payment method #%d could not be told to forget it (module #%d, %s).',
                (int) $method->getId(),
                (int) $method->getPaymentModuleId(),
                $throwable::class,
            ));
        }

        // Outside the best effort above: a transaction the provider ended cannot be given
        // back with what it held, so the removal must fail and its caller roll back.
        if ($transactionLost) {
            throw new \LogicException(\sprintf('The provider of stored payment method #%d (module %s) ended the transaction of the module removal: it is aborted.', (int) $method->getId(), (string) $module?->getCode()));
        }
    }

    /**
     * Locks the customers of $customerIds not in $alreadyLocked, in ascending order, so
     * that two removals locking the same customers do not wait on each other over them.
     *
     * @param array<mixed> $customerIds
     * @param list<int>    $alreadyLocked
     *
     * @return list<int> every customer now locked
     */
    private function lockTheCustomers(array $customerIds, array $alreadyLocked, ConnectionInterface $connection): array
    {
        $toLock = array_values(array_diff(array_unique(array_map(intval(...), $customerIds)), $alreadyLocked));

        if ([] === $toLock) {
            return $alreadyLocked;
        }

        sort($toLock);

        // By primary key, in ascending order: record locks only.
        $lockTheCustomers = $connection->prepare(\sprintf(
            'SELECT `id` FROM `customer` WHERE `id` IN (%s) ORDER BY `id` FOR UPDATE',
            implode(', ', array_fill(0, \count($toLock), '?')),
        ));
        $lockTheCustomers->execute($toLock);
        $lockTheCustomers->closeCursor();

        $this->forgetPooledMethods();

        return array_merge($alreadyLocked, $toLock);
    }

    /**
     * Drops the rows just read under a lock, by their key: the very rows the providers
     * are told about, and a record lock on each rather than a range of the index.
     *
     * @param array<CustomerPaymentMethod> $methods
     */
    private function dropByPrimaryKey(array $methods, ConnectionInterface $connection): void
    {
        CustomerPaymentMethodQuery::create()
            ->filterById(array_map(static fn (CustomerPaymentMethod $method): int => (int) $method->getId(), $methods))
            ->delete($connection);
        // Nor would a later read in the same request find the rows just dropped.
        $this->forgetPooledMethods();
    }

    private function lockTheCustomer(int $customerId, ConnectionInterface $connection): void
    {
        $lockTheCustomer = $connection->prepare('SELECT `id` FROM `customer` WHERE `id` = :customerId FOR UPDATE');
        $lockTheCustomer->bindValue(':customerId', $customerId, \PDO::PARAM_INT);
        $lockTheCustomer->execute();

        $this->forgetPooledMethods();
    }

    /**
     * Whatever is read from here on is read under the lock, but Propel hands back the copy
     * its instance pool already holds for a row rather than the row just read: one loaded
     * by the caller before the lock, by the API item provider for instance, with a default
     * another request has moved since. The copies are dropped, so that the reads that
     * follow return the rows as they are, and a setter given the value of a stale copy is
     * not taken for a change that needs no write.
     */
    private function forgetPooledMethods(): void
    {
        CustomerPaymentMethodTableMap::clearInstancePool();
    }

    /**
     * The reads below run with the customer locked. A plain read is right when the
     * transaction opened at that lock (see clearTheDefault()). Nested in a transaction a
     * caller opened and read in, its view may predate a write committed since, the same
     * token registered by the return URL of the payment for instance: the rows are then
     * read under a lock, as they are.
     */
    private function asTheyAre(CustomerPaymentMethodQuery $query, bool $readsSeeCommits): CustomerPaymentMethodQuery
    {
        if (!$readsSeeCommits) {
            $query->lockForUpdate();
        }

        return $query;
    }

    /**
     * The methods of the customer are read, and the module and the token compared here.
     * Filtered on the module as well, the read may intersect the customer_id and the
     * payment_module_id indexes (seen in EXPLAIN on MariaDB 10.11): under a lock, that
     * would hold the methods of every customer of the module. The comparison is exact,
     * where the collation of the column folds case and a provider reference does not.
     */
    private function alreadyStored(Customer $customer, Module $module, string $token, bool $readsSeeCommits, ConnectionInterface $connection): ?CustomerPaymentMethod
    {
        $candidates = $this->asTheyAre(
            CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId()),
            $readsSeeCommits,
        )->find($connection);

        foreach ($candidates as $candidate) {
            if ((int) $candidate->getPaymentModuleId() === (int) $module->getId()
                && hash_equals((string) $candidate->getToken(), $token)) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasAny(Customer $customer, bool $readsSeeCommits, ConnectionInterface $connection): bool
    {
        return null !== $this->asTheyAre(
            CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId()),
            $readsSeeCommits,
        )->findOne($connection);
    }

    /**
     * Called with the customer locked. An update filtered on the customer would read the
     * customer_id index under a lock, gap included: the first method of the neighbouring
     * customer falls in that gap, its registration would wait, and two first
     * registrations of two customers would each hold the gap the other writes in, a
     * deadlock. The default is read instead, then cleared by its key.
     *
     * That read is a plain one, and it is right when the transaction opened here: the
     * customer was locked before it read anything, so its read view was taken after the
     * commit of every other write on the customer, all of which lock the customer first.
     * Nested in a transaction a caller opened, the view may predate such a commit, and
     * the update filtered on the customer, which reads the rows as they are, is kept.
     */
    private function clearTheDefault(Customer $customer, CustomerPaymentMethod $keptMethod, bool $readsSeeCommits, ConnectionInterface $connection): void
    {
        $query = CustomerPaymentMethodQuery::create()
            ->filterByCustomerId($customer->getId())
            ->filterByIsDefault(true);

        if (!$keptMethod->isNew()) {
            $query->filterById($keptMethod->getId(), Criteria::NOT_EQUAL);
        }

        if (!$readsSeeCommits) {
            $query->update(['IsDefault' => false], $connection);
            $this->forgetPooledMethods();

            return;
        }

        $defaultIds = $query->select('Id')->find($connection)->toArray();

        if ([] === $defaultIds) {
            return;
        }

        // Propel keeps the pool of an update it takes for one by primary key, an IN on
        // the id included: the copy of the former default would still say it is.
        CustomerPaymentMethodQuery::create()
            ->filterById(array_map(intval(...), $defaultIds))
            ->update(['IsDefault' => false], $connection);
        $this->forgetPooledMethods();
    }

    /**
     * Called with the customer locked. A plain read is right when the transaction opened
     * at that lock (see clearTheDefault()); otherwise its read view may predate a method
     * registered or forgotten since, and the methods are read under a lock, as they are.
     */
    private function promoteTheMostRecent(int $customerId, bool $readsSeeCommits, ConnectionInterface $connection): void
    {
        $this->asTheyAre(
            CustomerPaymentMethodQuery::create()
                ->filterByCustomerId($customerId)
                ->orderByCreatedAt(Criteria::DESC)
                ->orderById(Criteria::DESC),
            $readsSeeCommits,
        )->findOne($connection)
            ?->setIsDefault(true)
            ->save($connection);
    }

    /**
     * Re-read under the lock, by id and by customer: an object the caller holds is not
     * proof of who owns the row.
     */
    private function ownedOrRefuse(Customer $customer, CustomerPaymentMethod $method, bool $readsSeeCommits, ConnectionInterface $connection): CustomerPaymentMethod
    {
        $owned = null === $method->getId()
            ? null
            : $this->asTheyAre(
                CustomerPaymentMethodQuery::create()
                    ->filterById($method->getId())
                    ->filterByCustomerId($customer->getId()),
                $readsSeeCommits,
            )->findOne($connection);

        if (null === $owned) {
            throw new PaymentMethodNotOwnedException();
        }

        return $owned;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(CustomerPaymentMethodTableMap::DATABASE_NAME);
    }
}

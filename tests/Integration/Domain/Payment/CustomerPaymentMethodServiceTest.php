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

namespace Thelia\Tests\Integration\Domain\Payment;

use Propel\Runtime\Connection\ConnectionInterface;
use Psr\Log\AbstractLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerPaymentMethodEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Domain\Payment\Exception\GuestCannotStorePaymentMethodException;
use Thelia\Domain\Payment\Exception\PaymentMethodNotOwnedException;
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;
use Thelia\Domain\Payment\Exception\StoredMethodsNotSupportedException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Order\RetryingPaymentModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

final class CustomerPaymentMethodServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CustomerPaymentMethodService $service;

    private Module $module;

    /**
     * @var list<array{string, callable}>
     */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
        $this->factory = $this->createFixtureFactory();
        $this->service = $this->getService(CustomerPaymentMethodService::class);
        $this->module = $this->paymentModuleRow(StoredMethodsPaymentModule::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    public function testRegisteringStoresTheMethodAndMakesTheFirstOneTheDefault(): void
    {
        $customer = $this->customer();

        $method = $this->service->register($customer, $this->module, 'pm_first', 'Visa ending 4242', new \DateTimeImmutable('2030-12-31'));

        $stored = CustomerPaymentMethodQuery::create()->findPk($method->getId());
        self::assertNotNull($stored);
        self::assertSame($customer->getId(), $stored->getCustomerId());
        self::assertSame($this->module->getId(), $stored->getPaymentModuleId());
        self::assertSame('pm_first', $stored->getToken());
        self::assertSame('Visa ending 4242', $stored->getLabel());
        self::assertSame('2030-12-31', $stored->getExpiresAt('Y-m-d'));
        self::assertTrue($stored->getIsDefault(), 'The first method of a customer is the default one.');
    }

    public function testRegisteringATokenAlreadyStoredRefreshesItInsteadOfAddingARow(): void
    {
        $customer = $this->customer();

        $first = $this->service->register($customer, $this->module, 'pm_same', 'Visa ending 4242', new \DateTimeImmutable('2030-12-31'));
        $second = $this->service->register($customer, $this->module, 'pm_same', 'Visa ending 4242 (renewed)', new \DateTimeImmutable('2032-06-30'));

        self::assertSame($first->getId(), $second->getId());
        self::assertCount(1, $this->service->listFor($customer));

        $stored = CustomerPaymentMethodQuery::create()->findPk($first->getId());
        self::assertSame('Visa ending 4242 (renewed)', $stored->getLabel());
        self::assertSame('2032-06-30', $stored->getExpiresAt('Y-m-d'));
    }

    /**
     * The column collation folds case, a provider reference does not.
     */
    public function testATokenDifferingOnlyByItsCaseIsAnotherMethod(): void
    {
        $customer = $this->customer();

        $this->service->register($customer, $this->module, 'pm_AbC', 'Visa ending 4242', null);
        $this->service->register($customer, $this->module, 'pm_abc', 'Visa ending 1111', null);

        self::assertCount(2, $this->service->listFor($customer));
    }

    public function testACustomerHasOneSingleDefaultMethod(): void
    {
        $customer = $this->customer();

        $first = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $second = $this->service->register($customer, $this->module, 'pm_2', 'Visa ending 2222', null, default: true);
        $third = $this->service->register($customer, $this->module, 'pm_3', 'Visa ending 3333', null);

        self::assertSame([$second->getId()], $this->defaultMethodIdsOf($customer));
        self::assertFalse(CustomerPaymentMethodQuery::create()->findPk($first->getId())->getIsDefault());
        self::assertFalse(CustomerPaymentMethodQuery::create()->findPk($third->getId())->getIsDefault());

        $this->service->setDefault($customer, $first);

        self::assertSame([$first->getId()], $this->defaultMethodIdsOf($customer));
        self::assertSame($first->getId(), $this->service->listFor($customer)[0]->getId(), 'The default method is listed first.');
    }

    public function testForgettingTheDefaultHandsItToTheMostRecentRemainingMethod(): void
    {
        $customer = $this->customer();

        $first = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $this->service->register($customer, $this->module, 'pm_2', 'Visa ending 2222', null);
        $third = $this->service->register($customer, $this->module, 'pm_3', 'Visa ending 3333', null);

        $this->service->forget($customer, $first);

        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($first->getId()));
        self::assertSame([$third->getId()], $this->defaultMethodIdsOf($customer));
    }

    public function testForgettingAsksTheProviderBeforeDroppingTheMethod(): void
    {
        $customer = $this->customer();
        $method = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);

        $this->service->forget($customer, $method);

        self::assertSame([$method->getId()], StoredMethodsPaymentModule::$forgottenMethodIds);
        self::assertSame([], $this->service->listFor($customer));
    }

    public function testNothingIsDroppedWhenTheProviderRefusesToForget(): void
    {
        $customer = $this->customer();
        $method = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        StoredMethodsPaymentModule::$providerRefusesToForget = true;

        try {
            $this->service->forget($customer, $method);
            self::fail('The refusal of the provider has to reach the caller.');
        } catch (StoredMethodRefusedException) {
        }

        $kept = CustomerPaymentMethodQuery::create()->findPk($method->getId());
        self::assertNotNull($kept, 'A method the provider refused to forget stays.');
        self::assertTrue($kept->getIsDefault(), 'And it stays the default one.');
    }

    public function testARefusalQuotingTheTokenCarriesItNeitherInItsMessageNorInTheLog(): void
    {
        $logger = $this->collectingLogger();
        $service = $this->serviceLoggingTo($logger);
        $customer = $this->customer();
        $method = $service->register($customer, $this->module, 'pm_secret', 'Visa ending 1111', null);
        StoredMethodsPaymentModule::$providerRefusesToForget = true;
        StoredMethodsPaymentModule::$refusalReason = "No such payment_method: 'pm_secret'";

        try {
            $service->forget($customer, $method);
            self::fail('The refusal of the provider has to reach the caller.');
        } catch (StoredMethodRefusedException $refusal) {
            self::assertStringNotContainsString('pm_secret', $refusal->getMessage());
            self::assertNull($refusal->getPrevious(), 'A previous exception would be logged with the whole chain.');
        }

        foreach ($logger->messages as $message) {
            self::assertStringNotContainsString('pm_secret', $message);
        }
    }

    /**
     * A provider SDK fails with its own exceptions, which quote what they were handed:
     * the caller gets a refusal with the core's wording, the method stays, and only the
     * class of the failure is written down.
     */
    public function testAProviderFailureIsARefusalThatCarriesItsMessageNeitherInTheResultNorInTheLog(): void
    {
        $logger = $this->collectingLogger();
        $service = $this->serviceLoggingTo($logger);
        $customer = $this->customer();
        $method = $service->register($customer, $this->module, 'pm_secret', 'Visa ending 1111', null);
        StoredMethodsPaymentModule::$failureMessage = 'pm_secret';

        try {
            $service->forget($customer, $method);
            self::fail('A failure of the provider has to reach the caller as a refusal.');
        } catch (StoredMethodRefusedException $refusal) {
            self::assertSame(StoredMethodRefusedException::MESSAGE, $refusal->getMessage());
            self::assertNull($refusal->getPrevious(), 'A previous exception would be logged with the whole chain.');
        }

        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($method->getId()), 'The provider did not forget it, the shop keeps it.');
        self::assertNotEmpty($logger->messages);

        foreach ($logger->messages as $message) {
            self::assertStringNotContainsString('pm_secret', $message);
        }

        self::assertStringContainsString(\RuntimeException::class, implode("\n", $logger->messages));
    }

    public function testForgettingAMethodThatIsNotTheDefaultLeavesTheDefaultWhereItIs(): void
    {
        $customer = $this->customer();

        $first = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $second = $this->service->register($customer, $this->module, 'pm_2', 'Visa ending 2222', null);

        $this->service->forget($customer, $second);

        self::assertSame([$first->getId()], $this->defaultMethodIdsOf($customer));
    }

    /**
     * A module that no longer implements the interface cannot be asked: the method goes
     * all the same, and the log says the provider was not told.
     */
    public function testAMethodWhoseModuleCanNoLongerBeAskedIsDroppedAllTheSame(): void
    {
        $logger = $this->collectingLogger();
        $service = $this->serviceLoggingTo($logger);
        $customer = $this->customer();
        $method = $service->register($customer, $this->module, 'pm_secret_reference', 'Visa ending 4242', null);
        $this->module->setFullNamespace(RetryingPaymentModule::class)->save($this->getPropelConnection());

        $service->forget($customer, $method);

        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($method->getId()));
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds);
        self::assertCount(1, $logger->messages);
        self::assertStringNotContainsString('pm_secret_reference', $logger->messages[0]);
    }

    public function testAnEmptyTokenOrABlankLabelIsRefused(): void
    {
        $customer = $this->customer();

        foreach ([['', 'Visa ending 4242'], ['pm_1', '   ']] as [$token, $label]) {
            try {
                $this->service->register($customer, $this->module, $token, $label, null);
                self::fail(\sprintf('The token "%s" with the label "%s" should have been refused.', $token, $label));
            } catch (\InvalidArgumentException) {
            }
        }

        self::assertSame([], $this->service->listFor($customer));
    }

    /**
     * The column holds 255 characters, counted as characters and not as bytes: a label
     * longer than that is refused before it reaches the database, and the refusal does
     * not repeat it.
     */
    public function testALabelLongerThanTheColumnIsRefusedWithoutBeingRepeated(): void
    {
        $customer = $this->customer();
        $tooLong = str_repeat('é', 255).'X';

        try {
            $this->service->register($customer, $this->module, 'pm_1', $tooLong, null);
            self::fail('A label of 256 characters should have been refused.');
        } catch (\InvalidArgumentException $refusal) {
            self::assertStringNotContainsString('éé', $refusal->getMessage());
        }

        self::assertSame([], $this->service->listFor($customer));

        $longest = str_repeat('é', 255);
        self::assertSame($longest, $this->service->register($customer, $this->module, 'pm_1', $longest, null)->getLabel());
    }

    public function testAGuestCannotStoreAMethod(): void
    {
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());

        try {
            $this->service->register($guest, $this->module, 'pm_guest', 'Visa ending 4242', null);
            self::fail('A guest has no account to keep a method on.');
        } catch (GuestCannotStorePaymentMethodException) {
        }

        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterByCustomerId($guest->getId())->count());
    }

    public function testAModuleWithoutStoredMethodsIsRefused(): void
    {
        $customer = $this->customer();

        $this->expectException(StoredMethodsNotSupportedException::class);

        $this->service->register($customer, $this->paymentModuleRow(RetryingPaymentModule::class), 'pm_1', 'Visa ending 4242', null);
    }

    public function testAModuleThatDoesNotOfferStoredMethodsAtTheMomentIsRefused(): void
    {
        $customer = $this->customer();
        StoredMethodsPaymentModule::$offersStoredMethods = false;

        $this->expectException(StoredMethodsNotSupportedException::class);

        $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 4242', null);
    }

    public function testAnotherCustomerCanNeitherReadNorForgetNorPromoteTheMethod(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $method = $this->service->register($owner, $this->module, 'pm_owner', 'Visa ending 4242', null);

        self::assertNull($this->service->findOwned($intruder, (int) $method->getId()));
        self::assertSame([], $this->service->listFor($intruder));
        self::assertNotNull($this->service->findOwned($owner, (int) $method->getId()));

        try {
            $this->service->forget($intruder, $method);
            self::fail('Another customer cannot forget the method.');
        } catch (PaymentMethodNotOwnedException) {
        }

        try {
            $this->service->setDefault($intruder, $method);
            self::fail('Another customer cannot make the method their default.');
        } catch (PaymentMethodNotOwnedException) {
        }

        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($method->getId()), 'The method of the owner is still there.');
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds, 'Its provider was not asked anything.');
    }

    public function testTheEventsAreDispatchedOnceTheRowIsWritten(): void
    {
        $customer = $this->customer();
        $created = [];
        $deleted = [];

        $this->listen(TheliaEvents::CUSTOMER_PAYMENT_METHOD_CREATE, static function (CustomerPaymentMethodEvent $event) use (&$created): void {
            $id = $event->getPaymentMethod()->getId();
            $created[] = [$id, null !== CustomerPaymentMethodQuery::create()->findPk($id)];
        });
        $this->listen(TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE, static function (CustomerPaymentMethodEvent $event) use (&$deleted): void {
            $id = $event->getPaymentMethod()->getId();
            $deleted[] = [$id, null !== CustomerPaymentMethodQuery::create()->findPk($id)];
        });

        $method = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111 (renewed)', null);
        $this->service->forget($customer, $method);

        self::assertSame([[$method->getId(), true]], $created, 'One creation, announced with the row written; a refresh is not a creation.');
        self::assertSame([[$method->getId(), false]], $deleted, 'One deletion, announced with the row gone.');
    }

    public function testForgettingAllTheMethodsOfACustomerDropsThemAndTellsTheProvidersAfterwards(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $first = $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $second = $this->service->register($customer, $this->module, 'pm_2', 'Visa ending 2222', null);
        $untouched = $this->service->register($other, $this->module, 'pm_3', 'Visa ending 3333', null);
        $deleted = [];
        $this->listen(TheliaEvents::CUSTOMER_PAYMENT_METHOD_DELETE, static function (CustomerPaymentMethodEvent $event) use (&$deleted): void {
            $deleted[] = $event->getPaymentMethod()->getId();
        });

        $tellTheProviders = $this->service->forgetAllOf($customer, $this->getPropelConnection());

        self::assertSame([], $this->service->listFor($customer), 'The rows go at once, in the caller transaction.');
        self::assertSame([], StoredMethodsPaymentModule::$forgottenMethodIds, 'The providers are not told before the caller commits.');
        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($untouched->getId()), 'Another customer keeps their methods.');

        foreach ($tellTheProviders as $tellTheProvider) {
            $tellTheProvider();
        }

        $expected = [$first->getId(), $second->getId()];
        $told = StoredMethodsPaymentModule::$forgottenMethodIds;
        sort($told);
        sort($deleted);
        self::assertSame($expected, $told);
        self::assertSame($expected, $deleted);
    }

    public function testForgettingAllTheMethodsOfACustomerRequiresTheCallerTransaction(): void
    {
        $customer = $this->customer();
        $this->service->register($customer, $this->module, 'pm_1', 'Visa ending 1111', null);
        $outsideAnyTransaction = $this->createMock(ConnectionInterface::class);
        $outsideAnyTransaction->method('inTransaction')->willReturn(false);

        try {
            $this->service->forgetAllOf($customer, $outsideAnyTransaction);
            self::fail('Outside a transaction the lock on the customer would be released at once.');
        } catch (\LogicException) {
        }

        self::assertCount(1, $this->service->listFor($customer));
    }

    public function testAProviderFailingAfterTheAnonymizationIsOnlyReported(): void
    {
        $logger = $this->collectingLogger();
        $service = $this->serviceLoggingTo($logger);
        $customer = $this->customer();
        $service->register($customer, $this->module, 'pm_secret_reference', 'Visa ending 1111', null);
        $tellTheProviders = $service->forgetAllOf($customer, $this->getPropelConnection());
        StoredMethodsPaymentModule::$providerRefusesToForget = true;

        foreach ($tellTheProviders as $tellTheProvider) {
            $tellTheProvider();
        }

        self::assertSame([], $service->listFor($customer), 'The erasure stands whatever the provider answers.');
        self::assertCount(1, $logger->messages, 'The failure is reported.');
        self::assertStringContainsString(StoredMethodRefusedException::class, $logger->messages[0]);
        self::assertStringNotContainsString('pm_secret_reference', $logger->messages[0], 'Without the token.');
    }

    public function testTheTokenStaysOutOfTheStringDebugAndSerializedForms(): void
    {
        $method = $this->service->register($this->customer(), $this->module, 'pm_secret_reference', 'Visa ending 4242', null);

        self::assertStringNotContainsString('pm_secret_reference', (string) $method);
        self::assertStringNotContainsString('pm_secret_reference', print_r($method, true));
        self::assertStringNotContainsString('pm_secret_reference', print_r($method->toArray(), true));
        self::assertStringNotContainsString('pm_secret_reference', $method->exportTo('JSON'));
        self::assertSame('pm_secret_reference', $method->getToken(), 'The getter is the one way to read it.');
        self::assertStringContainsString('Visa ending 4242', (string) $method);

        // Serializing an object that carries the row, a customer and its methods in a
        // session for instance, goes through without the token rather than failing.
        $serialized = serialize($method);
        self::assertStringNotContainsString('pm_secret_reference', $serialized);

        $restored = unserialize($serialized);
        self::assertInstanceOf(CustomerPaymentMethod::class, $restored);
        self::assertNull($restored->getToken());
        self::assertSame($method->getId(), $restored->getId());
        self::assertSame('Visa ending 4242', $restored->getLabel());
        self::assertFalse($restored->isModified(), 'Saving the copy writes nothing, least of all an empty token.');
    }

    /**
     * An \Error is a bug of the module, not an answer of the provider: it is an error in
     * the log, with where it was thrown and without its message; an \Exception stays a warning.
     */
    public function testAnErrorOfTheModuleIsLoggedAsAnErrorWhereAnExceptionIsAWarning(): void
    {
        $customer = $this->customer();

        foreach ([[false, 'warning'], [true, 'error']] as [$isAnError, $level]) {
            $logger = $this->collectingLogger();
            $service = $this->serviceLoggingTo($logger);
            $method = $service->register($customer, $this->module, 'pm_secret', 'Visa ending 1111', null);
            StoredMethodsPaymentModule::$failureMessage = 'pm_secret';
            StoredMethodsPaymentModule::$failureIsAnError = $isAnError;

            try {
                $service->forget($customer, $method);
                self::fail('A failure of the module has to reach the caller as a refusal.');
            } catch (StoredMethodRefusedException) {
            }

            self::assertSame([$level], $logger->levels);
            self::assertStringNotContainsString('pm_secret', $logger->messages[0]);

            if ($isAnError) {
                self::assertStringContainsString('StoredMethodsPaymentModule.php:', $logger->messages[0]);
            }
        }
    }

    private function serviceLoggingTo(AbstractLogger $logger): CustomerPaymentMethodService
    {
        return new CustomerPaymentMethodService(static::getContainer(), $this->dispatcher(), $logger);
    }

    private function collectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $messages = [];

            /**
             * @var list<string>
             */
            public array $levels = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->levels[] = (string) $level;
                $this->messages[] = (string) $message;
            }
        };
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * @param class-string<BaseModule> $moduleClass
     */
    private function paymentModuleRow(string $moduleClass): Module
    {
        $code = $moduleClass::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace($moduleClass)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }

    /**
     * @return list<int|null>
     */
    private function defaultMethodIdsOf(Customer $customer): array
    {
        return array_map(
            static fn (CustomerPaymentMethod $method): ?int => $method->getId(),
            array_values(array_filter(
                $this->service->listFor($customer),
                static fn (CustomerPaymentMethod $method): bool => true === $method->getIsDefault(),
            )),
        );
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}

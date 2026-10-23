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

namespace Thelia\Tests\Integration\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Thelia\Api\Bridge\Propel\Extension\CustomerPaymentMethodOwnershipExtension;
use Thelia\Api\Resource\CustomerPaymentMethod;
use Thelia\Api\Service\API\ResourceMemoizer;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * A front page has no firewall token: its customer lives in the Thelia session. The
 * stored payment methods read in process (`resources()`, `DataAccessService`) must
 * therefore be scoped to the session customer, and to nobody when there is none. It is
 * the only barrier of that read, so each case below fails if the filter goes.
 */
final class CustomerPaymentMethodSessionReadTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private const COLLECTION = '/api/front/account/payment_methods';

    private DataAccessService $dataAccess;

    private CustomerPaymentMethodService $service;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
        $this->dataAccess = $this->getService(DataAccessService::class);
        $this->service = $this->getService(CustomerPaymentMethodService::class);
        $this->module = $this->paymentModuleRow();
    }

    protected function tearDown(): void
    {
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

    public function testTheCollectionReadInProcessListsTheSessionCustomersMethodsOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);
        $customer = $factory->customer($factory->customerTitle());
        $mine = $this->service->register($customer, $this->module, 'pm_mine', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($customer);

        self::assertSame([$mine->getId()], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));
    }

    public function testTheItemOfAnotherCustomerIsAbsentFromAnInProcessRead(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $foreign = $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);
        $customer = $factory->customer($factory->customerTitle());
        $mine = $this->service->register($customer, $this->module, 'pm_mine', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($customer);

        self::assertNull($this->dataAccess->resources(self::COLLECTION.'/'.$foreign->getId()));

        $own = $this->dataAccess->resources(self::COLLECTION.'/'.$mine->getId());
        self::assertIsArray($own);
        self::assertSame($mine->getId(), $own['id']);
    }

    public function testNothingIsReadWithoutASessionCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $method = $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);

        self::assertSame([], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));
        self::assertNull($this->dataAccess->resources(self::COLLECTION.'/'.$method->getId()));
    }

    /**
     * The security expression refuses an anonymous in-process read first, so the query
     * scope is exercised alone here: it is the second barrier and must hold by itself.
     */
    public function testTheQueryScopeAloneReadsNothingWithoutTokenNorSessionCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);

        $tokenStorage = new TokenStorage();

        self::assertSame([], $this->idsOfQuery($this->scopedQuery($tokenStorage, new GetCollection(uriTemplate: '/front/account/payment_methods'))));
        self::assertNull($this->scopedQuery($tokenStorage, new Get(uriTemplate: '/front/account/payment_methods/{id}'))->findOne());
    }

    public function testTheQueryScopeAloneFallsBackOnTheSessionCustomerForTheCollectionAndTheItem(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $foreign = $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);
        $customer = $factory->customer($factory->customerTitle());
        $mine = $this->service->register($customer, $this->module, 'pm_mine', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($customer);

        $tokenStorage = new TokenStorage();

        self::assertSame([$mine->getId()], $this->idsOfQuery($this->scopedQuery($tokenStorage, new GetCollection(uriTemplate: '/front/account/payment_methods'))));
        self::assertNull($this->scopedQuery($tokenStorage, new Get(uriTemplate: '/front/account/payment_methods/{id}'))->filterById($foreign->getId())->findOne());
        self::assertNotNull($this->scopedQuery($tokenStorage, new Get(uriTemplate: '/front/account/payment_methods/{id}'))->filterById($mine->getId())->findOne());
    }

    public function testNothingIsReadOnceTheSessionCustomerLoggedOut(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->service->register($customer, $this->module, 'pm_mine', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($customer);
        $this->logoutCustomer();

        self::assertSame([], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));
    }

    public function testAGuestInTheSessionOwnsNoMethod(): void
    {
        $factory = $this->createFixtureFactory();
        $guest = $factory->customer($factory->customerTitle());
        // Registration refuses a guest, so the row turns guest after the method was stored.
        $this->service->register($guest, $this->module, 'pm_guest', 'Visa ending 3333', null);
        $guest->setIsGuest(1)->save($this->getPropelConnection());
        $this->loginAsCustomerInSession($guest);

        self::assertSame([], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));
    }

    public function testTheTokenCustomerWinsOverTheSessionCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $sessionCustomer = $factory->customer($factory->customerTitle());
        $this->service->register($sessionCustomer, $this->module, 'pm_session', 'Visa ending 1111', null);
        $tokenCustomer = $factory->customer($factory->customerTitle());
        $viaToken = $this->service->register($tokenCustomer, $this->module, 'pm_token', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($sessionCustomer);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($tokenCustomer, 'api', $tokenCustomer->getRoles()));

        self::assertSame([$viaToken->getId()], $this->idsOfQuery($this->scopedQuery($tokenStorage, new GetCollection(uriTemplate: '/front/account/payment_methods'))));
    }

    public function testATokenThatIsNotACustomerIsNeverReplacedByTheSessionCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->service->register($customer, $this->module, 'pm_mine', 'Visa ending 2222', null);
        $this->loginAsCustomerInSession($customer);
        $admin = $factory->admin();

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'api', $admin->getRoles()));

        self::assertSame([], $this->idsOfQuery($this->scopedQuery($tokenStorage, new GetCollection(uriTemplate: '/front/account/payment_methods'))));
        self::assertNull($this->scopedQuery($tokenStorage, new Get(uriTemplate: '/front/account/payment_methods/{id}'))->findOne());
    }

    public function testTheAdminEndpointsStayOutOfScope(): void
    {
        $factory = $this->createFixtureFactory();
        $first = $this->service->register($factory->customer($factory->customerTitle()), $this->module, 'pm_a', 'Visa ending 1111', null);
        $second = $this->service->register($factory->customer($factory->customerTitle()), $this->module, 'pm_b', 'Visa ending 2222', null);

        $ids = $this->idsOfQuery($this->scopedQuery(new TokenStorage(), new GetCollection(uriTemplate: '/admin/customer_payment_methods')));

        self::assertContains($first->getId(), $ids);
        self::assertContains($second->getId(), $ids);
    }

    /**
     * A read that looks for a customer must not be what opens the session: a request
     * whose session is still behind its lazy factory keeps it there.
     */
    public function testTheFallbackLeavesASessionBehindItsLazyFactoryAlone(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);

        $created = false;
        $request = Request::create('http://localhost');
        $request->setSessionFactory(static function () use (&$created): Session {
            $created = true;

            return new Session(new MockArraySessionStorage());
        });
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $query = CustomerPaymentMethodQuery::create();
        (new CustomerPaymentMethodOwnershipExtension(new TokenStorage(), new SecurityContext($requestStack), $requestStack))
            ->applyToCollection($query, CustomerPaymentMethod::class, new GetCollection(uriTemplate: '/front/account/payment_methods'));

        self::assertSame([], $this->idsOfQuery($query));
        self::assertFalse($created, 'Reading for a customer must not create the session.');
    }

    /**
     * The answer of an account path depends on the customer of the session, which the
     * memoization of a request does not know: the customer changes, so does the answer.
     */
    public function testAnAccountReadIsNotServedFromAnotherCustomersMemoizedAnswer(): void
    {
        $factory = $this->createFixtureFactory();
        $first = $factory->customer($factory->customerTitle());
        $firstMethod = $this->service->register($first, $this->module, 'pm_first', 'Visa ending 1111', null);
        $second = $factory->customer($factory->customerTitle());
        $secondMethod = $this->service->register($second, $this->module, 'pm_second', 'Visa ending 2222', null);

        $this->loginAsCustomerInSession($first);
        self::assertSame([$firstMethod->getId()], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));

        $this->logoutCustomer();
        $this->loginAsCustomerInSession($second);

        self::assertSame([$secondMethod->getId()], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));

        $this->logoutCustomer();

        self::assertSame([], $this->idsOf($this->dataAccess->resources(self::COLLECTION)));
    }

    /**
     * Control of the exclusion above: it covers the stored payment methods alone, the other
     * account reads of a page (the order is read three times by the checkout page) stay
     * memoized in the request.
     */
    public function testAnotherAccountReadIsStillMemoized(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->loginAsCustomerInSession($customer);
        $memoizer = $this->getService(ResourceMemoizer::class);
        $memoizer->clear();

        $this->dataAccess->resources('/api/front/account/addresses');
        self::assertSame(1, $this->memoizedAnswers($memoizer), 'An account read other than the payment methods is memoized.');

        $this->dataAccess->resources(self::COLLECTION);
        self::assertSame(1, $this->memoizedAnswers($memoizer), 'The stored payment methods are never memoized.');
    }

    private function memoizedAnswers(ResourceMemoizer $memoizer): int
    {
        return \count((new \ReflectionProperty($memoizer, 'store'))->getValue($memoizer));
    }

    /**
     * Control: the rows of the other customers are really in the table, so the empty
     * answers above do not come from an empty table.
     */
    public function testTheOtherCustomersRowsAreInTheTable(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $foreign = $this->service->register($other, $this->module, 'pm_other', 'Visa ending 1111', null);

        self::assertNotNull(CustomerPaymentMethodQuery::create()->findPk($foreign->getId()));
    }

    private function scopedQuery(TokenStorageInterface $tokenStorage, GetCollection|Get $operation): CustomerPaymentMethodQuery
    {
        $query = CustomerPaymentMethodQuery::create();
        $extension = new CustomerPaymentMethodOwnershipExtension($tokenStorage, $this->getService(SecurityContext::class), $this->getService('request_stack'));

        $operation instanceof GetCollection
            ? $extension->applyToCollection($query, CustomerPaymentMethod::class, $operation)
            : $extension->applyToItem($query, CustomerPaymentMethod::class, $operation);

        return $query;
    }

    /**
     * @return list<int>
     */
    private function idsOfQuery(CustomerPaymentMethodQuery $query): array
    {
        return array_map(intval(...), $query->select('Id')->find()->toArray());
    }

    /**
     * @return list<int>
     */
    private function idsOf(mixed $collection): array
    {
        // null: the security expression of the operation refused the read before the query ran.
        if (null === $collection) {
            return [];
        }

        self::assertIsArray($collection);

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $collection));
    }

    private function paymentModuleRow(): Module
    {
        $code = StoredMethodsPaymentModule::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}

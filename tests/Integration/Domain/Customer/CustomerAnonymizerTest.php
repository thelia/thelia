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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\AddressQuery;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerVersionQuery;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CustomerAnonymizerTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAnonymizeKeepsTheOrderAndItsAmounts(): void
    {
        $customer = $this->createCustomerWithHistory();
        $order = $customer->getOrders()->getFirst();
        self::assertInstanceOf(Order::class, $order);

        $orderId = $order->getId();
        $orderReference = $order->getRef();

        $this->anonymize($customer);

        $reloadedOrder = OrderQuery::create()->findPk($orderId);
        self::assertNotNull($reloadedOrder, 'Anonymizing a customer must not delete the orders.');
        self::assertSame($orderReference, $reloadedOrder->getRef());
        self::assertSame($customer->getId(), $reloadedOrder->getCustomerId());
        self::assertSame('12.500000', $reloadedOrder->getPostage());
        self::assertSame('2.500000', $reloadedOrder->getPostageTax());

        $orderProduct = $reloadedOrder->getOrderProducts()->getFirst();
        self::assertNotNull($orderProduct);
        self::assertSame('99.990000', $orderProduct->getPrice());
        self::assertSame(2.0, $orderProduct->getQuantity());
    }

    public function testAnonymizeErasesTheAccountIdentity(): void
    {
        $customer = $this->createCustomerWithHistory();

        $this->anonymize($customer);

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);
        self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $reloaded->getFirstname());
        self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $reloaded->getLastname());
        self::assertStringEndsWith('@'.CustomerAnonymizer::ANONYMIZED_EMAIL_DOMAIN, (string) $reloaded->getEmail());
        self::assertSame('', $reloaded->getPassword());
        self::assertNull($reloaded->getAlgo());
        self::assertNull($reloaded->getRememberMeToken());
        self::assertNull($reloaded->getRememberMeSerial());
        self::assertNull($reloaded->getConfirmationToken());
        self::assertSame(0, $reloaded->getEnable());
    }

    public function testAnonymizeStampsTheAccountWithTheErasureDate(): void
    {
        $customer = $this->createCustomerWithHistory();
        self::assertNull($customer->getAnonymizedAt(), 'An account carrying an identity must not be marked.');

        $this->anonymize($customer);

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull(
            $reloaded->getAnonymizedAt(),
            'Nothing else tells an anonymous account from an account that simply has no name yet.',
        );
    }

    /**
     * The marker records when the data actually went away, so replaying the
     * operation must not push the date forward.
     */
    public function testAnonymizingTwiceKeepsTheFirstErasureDate(): void
    {
        $customer = $this->createCustomerWithHistory();
        $customer->setAnonymizedAt(new \DateTime('2020-01-02 03:04:05'))->save($this->getPropelConnection());

        $this->anonymize($customer);

        $reloaded = CustomerQuery::create()->findPk($customer->getId());
        self::assertNotNull($reloaded);
        self::assertSame('2020-01-02 03:04:05', $reloaded->getAnonymizedAt('Y-m-d H:i:s'));
    }

    public function testAnonymizeLeavesNoIdentifyingDataBehind(): void
    {
        $customer = $this->createCustomerWithHistory();
        $customerId = $customer->getId();

        $this->anonymize($customer);

        self::assertSame(
            0,
            AddressQuery::create()->filterByCustomerId($customerId)->count(),
            'The address book must be deleted.',
        );
        self::assertSame(
            0,
            CartQuery::create()->filterByCustomerId($customerId)->count(),
            'The carts must be deleted.',
        );
        self::assertSame(
            0,
            NewsletterQuery::create()->filterByEmail('anonymizer-subject@test.com')->count(),
            'The newsletter subscription must be deleted.',
        );
        self::assertSame(
            0,
            CustomerVersionQuery::create()->filterById($customerId)->count(),
            'The versionable history keeps a readable copy of the identity and must be deleted.',
        );

        $order = OrderQuery::create()->filterByCustomerId($customerId)->findOne();
        self::assertNotNull($order);

        foreach ([$order->getInvoiceOrderAddressId(), $order->getDeliveryOrderAddressId()] as $orderAddressId) {
            $orderAddress = OrderAddressQuery::create()->findPk($orderAddressId);
            self::assertNotNull($orderAddress);
            self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $orderAddress->getLastname());
            self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $orderAddress->getAddress1());
            self::assertNull($orderAddress->getPhone());
            self::assertNull($orderAddress->getCompany());
            self::assertNotNull(
                $orderAddress->getCountryId(),
                'The country justifies the tax rate applied on the order and must be kept.',
            );
        }
    }

    /**
     * A back-office edit of a customer writes their identity twice in the
     * audit trail: in the message, composed from the name, and in the
     * serialized request, which holds the whole posted form.
     */
    public function testAnonymizeErasesTheIdentityLeftInTheAdminLog(): void
    {
        $customer = $this->createCustomerWithHistory();

        $editLog = $this->adminLog(
            AdminResources::CUSTOMER,
            $customer->getId(),
            'UPDATE',
            'Customer Anonymizer Subject (ID '.$customer->getId().') modified',
            'POST /admin/customer/save lastname=Subject&email=anonymizer-subject@test.com',
        );

        $this->anonymize($customer);

        $rewritten = AdminLogQuery::create()->findPk($editLog->getId());
        self::assertNotNull($rewritten);
        self::assertSame(CustomerAnonymizer::ANONYMIZED_ADMIN_LOG_MESSAGE, $rewritten->getMessage());
        self::assertNull($rewritten->getRequest());

        self::assertSame('admin-login', $rewritten->getAdminLogin(), 'An erasure must not erase who performed the logged action.');
        self::assertSame('UPDATE', $rewritten->getAction());
        self::assertSame($customer->getId(), $rewritten->getResourceId());
        self::assertSame('2020-01-02 03:04:05', $rewritten->getCreatedAt('Y-m-d H:i:s'), 'The trail must still say when the action happened.');
        self::assertNotSame(
            '2020-01-02 03:04:05',
            $rewritten->getUpdatedAt('Y-m-d H:i:s'),
            'The rewrite is itself recorded, rather than letting an audited row change silently.',
        );
    }

    /**
     * The rewrite is bounded to the rows about that customer: an audit trail
     * that erased entries about other resources, or about other customers,
     * would destroy evidence unrelated to the erasure.
     */
    public function testAnonymizeLeavesTheAdminLogOfOtherResourcesUntouched(): void
    {
        $customer = $this->createCustomerWithHistory();
        $otherCustomer = $this->factory->customer($this->factory->customerTitle());

        $orderLog = $this->adminLog(AdminResources::ORDER, $customer->getId(), 'UPDATE', 'Order 12 modified', 'order payload');
        $otherCustomerLog = $this->adminLog(AdminResources::CUSTOMER, $otherCustomer->getId(), 'UPDATE', 'Customer Other Person modified', 'other payload');

        $this->anonymize($customer);

        $reloadedOrderLog = AdminLogQuery::create()->findPk($orderLog->getId());
        self::assertNotNull($reloadedOrderLog);
        self::assertSame('Order 12 modified', $reloadedOrderLog->getMessage());
        self::assertSame('order payload', $reloadedOrderLog->getRequest());
        self::assertSame('2020-01-02 03:04:05', $reloadedOrderLog->getUpdatedAt('Y-m-d H:i:s'), 'The row must not be written at all.');

        $reloadedOtherCustomerLog = AdminLogQuery::create()->findPk($otherCustomerLog->getId());
        self::assertNotNull($reloadedOtherCustomerLog);
        self::assertSame('Customer Other Person modified', $reloadedOtherCustomerLog->getMessage());
        self::assertSame('other payload', $reloadedOtherCustomerLog->getRequest());
    }

    public function testAnonymizeCallsThePersonalDataProviders(): void
    {
        $customer = $this->createCustomerWithHistory();

        $provider = new class implements CustomerPersonalDataProviderInterface {
            /** @var array<int, int> */
            public array $anonymizedCustomerIds = [];

            public function getPersonalDataSectionName(): string
            {
                return 'recording';
            }

            public function exportPersonalData(Customer $customer): array
            {
                return ['seen' => $customer->getId()];
            }

            public function anonymizePersonalData(Customer $customer): void
            {
                $this->anonymizedCustomerIds[] = $customer->getId();
            }
        };

        (new CustomerAnonymizer([$provider]))->anonymize($customer);

        self::assertSame([$customer->getId()], $provider->anonymizedCustomerIds);
    }

    /**
     * Anonymization runs in a transaction, so a module that fails aborts the
     * whole operation instead of leaving a half-erased account behind. Only
     * the propagation is asserted here: this test itself runs inside the
     * transaction opened by IntegrationTestCase, where the inner rollback is
     * deferred to the outer one.
     */
    public function testAnonymizeStopsWhenAProviderFails(): void
    {
        $customer = $this->createCustomerWithHistory();

        $failingProvider = new class implements CustomerPersonalDataProviderInterface {
            public function getPersonalDataSectionName(): string
            {
                return 'failing';
            }

            public function exportPersonalData(Customer $customer): array
            {
                return [];
            }

            public function anonymizePersonalData(Customer $customer): void
            {
                throw new \RuntimeException('module failure');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('module failure');

        (new CustomerAnonymizer([$failingProvider]))->anonymize($customer);
    }

    private function adminLog(string $resource, int $resourceId, string $action, string $message, string $request): AdminLog
    {
        $adminLog = new AdminLog();
        $adminLog
            ->setAdminLogin('admin-login')
            ->setResource($resource)
            ->setResourceId($resourceId)
            ->setAction($action)
            ->setMessage($message)
            ->setRequest($request)
            ->setCreatedAt(new \DateTime('2020-01-02 03:04:05'))
            ->setUpdatedAt(new \DateTime('2020-01-02 03:04:05'))
            ->save($this->getPropelConnection());

        return $adminLog;
    }

    private function anonymize(Customer $customer): void
    {
        $this->getService(EventDispatcherInterface::class)->dispatch(
            new CustomerAnonymizeEvent($customer),
            TheliaEvents::CUSTOMER_ANONYMIZE,
        );
    }

    /**
     * A customer with everything core knows how to store about a person:
     * an account, an address, a newsletter subscription, a cart and a paid
     * order carrying a product line.
     */
    private function createCustomerWithHistory(): Customer
    {
        $customer = $this->factory->customer(
            $this->factory->customerTitle(),
            [
                'firstname' => 'Anonymizer',
                'lastname' => 'Subject',
                'email' => 'anonymizer-subject@test.com',
            ],
        );

        $this->factory->address($customer);

        $newsletter = new Newsletter();
        $newsletter
            ->setEmail('anonymizer-subject@test.com')
            ->setFirstname('Anonymizer')
            ->setLastname('Subject')
            ->save($this->getPropelConnection());

        $order = $this->factory->order($customer, ['postage' => '12.5', 'postageTax' => '2.5']);

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('REF-ANONYMIZER')
            ->setProductSaleElementsRef('REF-ANONYMIZER-PSE')
            ->setTitle('Product')
            ->setQuantity(2)
            ->setPrice('99.99')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        // The factory already created the cart backing the order; add one more
        // so that a cart without an order is covered too.
        $this->factory->cart($customer);

        return $customer;
    }
}

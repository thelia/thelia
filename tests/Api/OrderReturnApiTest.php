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

namespace Thelia\Tests\Api;

use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The return API keeps every customer to their own returns, refuses a customer
 * token on the administration operations, and lets an authorized administrator
 * drive the return through its cycle.
 */
final class OrderReturnApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testACustomerOnlySeesTheirOwnReturns(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();

        $return = $this->returnFor($owner, OrderReturnStatus::CODE_REQUESTED);

        $ownerToken = $this->authenticateAsCustomer($owner);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $ownerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);

        $strangerToken = $this->authenticateAsCustomer($stranger);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $strangerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(0, $response);

        self::assertNotNull($return->getId());
    }

    public function testACustomerCannotReadAnotherCustomersReturn(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();

        $return = $this->returnFor($owner, OrderReturnStatus::CODE_REQUESTED);

        $token = $this->authenticateAsCustomer($stranger);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns/'.$return->getId(), token: $token);

        self::assertContains($response->getStatusCode(), [403, 404]);
    }

    public function testAClientTokenIsRejectedOnTheAdminTransition(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_RECEIVED],
            token: $token,
        );

        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testAnAdminMovesAReturnThroughItsCycle(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_RECEIVED],
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);

        $received = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_RECEIVED);
        $return->reload(false, $this->getPropelConnection());
        self::assertSame((int) $received?->getId(), (int) $return->getStatusId());
    }

    public function testAnAdminCannotApplyAnIllegalTransition(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_REQUESTED);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_SETTLED],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testACustomerOpensAReturnOnTheirOwnOrder(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');

        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->findOne($this->getPropelConnection());

        self::assertNotNull($created);
        self::assertFalse((bool) $created->getCreatedByAdmin());
        self::assertSame(
            (int) OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED),
            (int) $created->getStatusId(),
        );
        self::assertSame(10.0, (float) $created->getRefundAmount());
    }

    public function testAnAdminOpensAReturnOnBehalfOfACustomer(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->findOne($this->getPropelConnection());

        self::assertNotNull($created);
        self::assertTrue((bool) $created->getCreatedByAdmin());
    }

    public function testACustomerCannotOpenAReturnWhenTheFeatureIsDisabled(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '0');

        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testACustomerCannotReturnMoreThanTheOrderedQuantity(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');

        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 5.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function returnFor(Customer $customer, string $statusCode): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->orderProductFor($order);

        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        return $return;
    }

    /**
     * @return array{0: Order, 1: OrderProductModel}
     */
    private function paidOrderWithProduct(Customer $customer): array
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        return [$order, $this->orderProductFor($order)];
    }

    private function orderProductFor(Order $order): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}

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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The payment parts of an order are read through the order, in settlement order, by
 * an administrator and by the customer who owns it, and by nobody else.
 */
final class OrderPaymentPartApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAnAdministratorReadsThePartsOfAnOrderInSettlementOrder(): void
    {
        $order = $this->factory->order($this->customer());
        // Written out of order: the position decides, not the id.
        $second = $this->part($order, '12.500000', 2, OrderPaymentPartState::PENDING, null, 'Gift card');
        $first = $this->part($order, '30.000000', 1, OrderPaymentPartState::SETTLED, $this->paymentModule(), null, true);

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $this->authenticateAsAdmin());
        self::assertJsonResponseSuccessful($response);
        $parts = $this->decode($response)['paymentParts'] ?? null;

        self::assertIsArray($parts);
        self::assertCount(2, $parts);
        self::assertSame([$first->getId(), $second->getId()], array_column($parts, 'id'));
        self::assertSame(['30.000000', '12.500000'], array_column($parts, 'amount'));
        self::assertSame(['settled', 'pending'], array_column($parts, 'state'));
        self::assertSame([true, false], array_column($parts, 'isPrimary'));
        self::assertSame([1, 2], array_column($parts, 'position'));
        self::assertNotEmpty($parts[0]['paymentModule']);
        self::assertNull($parts[1]['paymentModule'] ?? null, 'A deleted module is no longer linked.');
        self::assertSame('Gift card', $parts[1]['paymentModuleTitle']);
    }

    public function testTheAdminCollectionIsFilteredByOrder(): void
    {
        $order = $this->factory->order($this->customer());
        $other = $this->factory->order($this->customer());
        $this->part($order, '10.000000', 1);
        $this->part($other, '99.000000', 1);

        $response = $this->jsonRequest('GET', '/api/admin/order_payment_parts?order.id='.$order->getId(), token: $this->authenticateAsAdmin());
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
        self::assertSame('10.000000', $this->decodeMembers($response)[0]['amount'] ?? null);
    }

    public function testACustomerReadsThePartsOfTheirOwnOrder(): void
    {
        $owner = $this->customer();
        $order = $this->factory->order($owner);
        $this->part($order, '20.000000', 2, OrderPaymentPartState::PENDING, null, 'Gift card');
        $this->part($order, '5.000000', 1, OrderPaymentPartState::SETTLED, null, 'Card', true);

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($owner));
        self::assertJsonResponseSuccessful($response);
        $parts = $this->decode($response)['paymentParts'] ?? null;

        self::assertIsArray($parts);
        self::assertSame(['5.000000', '20.000000'], array_column($parts, 'amount'));
        self::assertSame(['Card', 'Gift card'], array_column($parts, 'paymentModuleTitle'));
    }

    public function testACustomerDoesNotReadThePartsOfAnotherCustomersOrder(): void
    {
        $order = $this->factory->order($this->customer());
        $this->part($order, '77.000000', 1, OrderPaymentPartState::SETTLED, null, 'Secret card');

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($this->customer()));

        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND]);
        self::assertStringNotContainsString('77.000000', (string) $response->getContent());
        self::assertStringNotContainsString('Secret card', (string) $response->getContent());
    }

    public function testAnOrderWithoutPartsHasAnEmptyList(): void
    {
        $owner = $this->customer();
        $order = $this->factory->order($owner);

        $admin = $this->decode($this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $this->authenticateAsAdmin()));
        $front = $this->decode($this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($owner)));

        self::assertSame([], $admin['paymentParts'] ?? null);
        self::assertSame([], $front['paymentParts'] ?? null);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function part(
        Order $order,
        string $amount,
        int $position,
        OrderPaymentPartState $state = OrderPaymentPartState::PENDING,
        ?Module $module = null,
        ?string $title = null,
        bool $primary = false,
    ): OrderPaymentPart {
        $part = new OrderPaymentPart();
        $part
            ->setOrderId($order->getId())
            ->setPaymentModuleId($module?->getId())
            ->setPaymentModuleTitle($title)
            ->setAmount($amount)
            ->setState($state->value)
            ->setIsPrimary($primary)
            ->setPosition($position)
            ->save($this->getPropelConnection());

        return $part;
    }

    private function paymentModule(): Module
    {
        $existing = ModuleQuery::create()->filterByCode('OrderPaymentPartApiPayment')->findOne($this->getPropelConnection());

        if (null !== $existing) {
            return $existing;
        }

        $module = new Module();
        $module
            ->setCode('OrderPaymentPartApiPayment')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace('OrderPaymentPartApiPayment\\OrderPaymentPartApiPayment')
            ->save($this->getPropelConnection());

        return $module;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeMembers(Response $response): array
    {
        return $this->decode($response)['member'] ?? $this->decode($response)['hydra:member'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}

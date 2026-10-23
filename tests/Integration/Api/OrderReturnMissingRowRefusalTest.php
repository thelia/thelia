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

use ApiPlatform\Metadata\Post;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderProduct as OrderProductResource;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Resource\OrderReturnLine as OrderReturnLineResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Api\State\Processor\OrderReturnFrontCreateProcessor;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An order, or an order product, that is gone by the time the return is
 * written is refused with the words used for one that is not the customer's.
 * The administration names real rows and keeps its precise messages.
 */
final class OrderReturnMissingRowRefusalTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheFrontProcessorRefusesAMissingOrderAsAForeignOne(): void
    {
        $customer = $this->customer();
        $foreign = $this->factory->order($this->customer(), ['statusCode' => OrderStatus::CODE_PAID]);
        $foreignProduct = $this->orderProduct((int) $foreign->getId());

        $messages = [];
        foreach ([(int) $foreign->getId(), (int) $foreign->getId() + 1_000_000] as $orderId) {
            $messages[] = $this->refusalOf($this->frontProcessor($customer), $this->request($orderId, (int) $foreignProduct->getId()));
        }

        self::assertSame(ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE, $messages[0]);
        self::assertSame($messages[0], $messages[1]);
    }

    public function testTheHydratorRefusesAMissingOrderWithTheFrontWordsOnlyOnTheFront(): void
    {
        $customer = $this->customer();
        $missing = $this->request(2_000_000_000, 1);

        self::assertSame(
            ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE,
            $this->hydrationRefusal($customer, $missing, byAdmin: false),
        );
        self::assertSame(
            'The order does not exist.',
            $this->hydrationRefusal($customer, $missing, byAdmin: true),
        );
    }

    public function testTheHydratorRefusesAMissingOrderProductWithTheFrontWordsOnlyOnTheFront(): void
    {
        $customer = $this->customer();
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $request = $this->request((int) $order->getId(), 2_000_000_000);

        self::assertSame(
            ReturnEligibilityChecker::ORDER_PRODUCT_NOT_USABLE_MESSAGE,
            $this->hydrationRefusal($customer, $request, byAdmin: false),
        );
        self::assertSame(
            'Unknown order product in a return line.',
            $this->hydrationRefusal($customer, $request, byAdmin: true),
        );
    }

    private function frontProcessor(Customer $customer): OrderReturnFrontCreateProcessor
    {
        $this->getService(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($customer, 'api', []));

        return $this->getService(OrderReturnFrontCreateProcessor::class);
    }

    private function refusalOf(OrderReturnFrontCreateProcessor $processor, OrderReturnResource $request): string
    {
        try {
            $processor->process($request, new Post());
        } catch (UnprocessableEntityHttpException $exception) {
            return $exception->getMessage();
        }

        self::fail('The request was not refused.');
    }

    private function hydrationRefusal(Customer $customer, OrderReturnResource $request, bool $byAdmin): string
    {
        try {
            $this->getService(OrderReturnHydrator::class)->hydrate($request, $customer, $byAdmin);
        } catch (ReturnNotAllowedException $exception) {
            return $exception->getMessage();
        }

        self::fail('The request was not refused.');
    }

    private function request(int $orderId, int $orderProductId): OrderReturnResource
    {
        return (new OrderReturnResource())
            ->setOrder((new OrderResource())->setId($orderId))
            ->setOrderReturnLines([
                (new OrderReturnLineResource())
                    ->setOrderProduct((new OrderProductResource())->setId($orderProductId))
                    ->setQuantity(1.0),
            ]);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function orderProduct(int $orderId): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId($orderId)
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

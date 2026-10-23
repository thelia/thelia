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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderProduct as OrderProductResource;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Resource\OrderReturnLine as OrderReturnLineResource;
use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Api\State\Processor\OrderReturnAdminCreateProcessor;
use Thelia\Api\State\Processor\OrderReturnFrontCreateProcessor;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturnLine as OrderReturnLineModel;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The processors keep the lines of a return new on their own, whatever the
 * serializer let through: a line that already carries an id, or a Propel model,
 * is refused before anything is locked or written. The API tests reach the
 * same refusal through the denormalizer first and cannot tell the two apart.
 */
final class OrderReturnExistingLineProcessorTest extends IntegrationTestCase
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function existingLines(): iterable
    {
        yield 'a line with an id' => ['id'];
        yield 'a line loaded from the database' => ['model'];
    }

    #[DataProvider('existingLines')]
    public function testTheFrontProcessorRefusesAnExistingLine(string $form): void
    {
        $customer = $this->customer();
        $request = $this->requestWithExistingLine($customer, $form);

        $this->getService(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($customer, 'api', []));

        try {
            $this->getService(OrderReturnFrontCreateProcessor::class)->process($request, new Post());
            self::fail('The request was not refused.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame(OrderReturnHydrator::EXISTING_LINE_MESSAGE, $exception->getMessage());
        }

        self::assertSame(0, OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->count($this->getPropelConnection()));
    }

    #[DataProvider('existingLines')]
    public function testTheAdminProcessorRefusesAnExistingLine(string $form): void
    {
        $customer = $this->customer();
        $request = $this->requestWithExistingLine($customer, $form);

        try {
            $this->getService(OrderReturnAdminCreateProcessor::class)->process($request, new Post());
            self::fail('The request was not refused.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertSame(OrderReturnHydrator::EXISTING_LINE_MESSAGE, $exception->getMessage());
        }

        self::assertSame(0, OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->count($this->getPropelConnection()));
    }

    private function requestWithExistingLine(Customer $customer, string $form): OrderReturnResource
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProduct((int) $order->getId());

        $line = (new OrderReturnLineResource())
            ->setOrderProduct((new OrderProductResource())->setId((int) $orderProduct->getId()))
            ->setQuantity(1.0);

        if ('id' === $form) {
            $line->setId(999_999_999);
        } else {
            $line->setPropelModel(new OrderReturnLineModel());
        }

        return (new OrderReturnResource())
            ->setOrder((new OrderResource())->setId((int) $order->getId()))
            ->setOrderReturnLines([$line]);
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

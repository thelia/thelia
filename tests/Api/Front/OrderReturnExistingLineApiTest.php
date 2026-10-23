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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A return opened by a customer is made of new lines only. A line of the body
 * that names an existing return line, whatever the form, is refused before
 * that line is read, and the line, which may belong to another customer, is
 * left as it was.
 */
final class OrderReturnExistingLineApiTest extends ApiTestCase
{
    private const EXISTING_LINE_MESSAGE = 'A return line must be a new line, not an existing one.';

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
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{list<mixed>, string}>
     */
    public static function existingLineForms(): iterable
    {
        yield 'admin IRI as a string' => [['{adminLineIri}'], 'jsonld'];
        yield 'front IRI as a string' => [['{frontLineIri}'], 'jsonld'];
        yield 'IRI of a line that does not exist' => [['{missingLineIri}'], 'jsonld'];
        yield 'bare integer id' => [['{lineIdInt}'], 'jsonld'];
        yield 'bare numeric string id' => [['{lineIdString}'], 'jsonld'];
        yield 'object with only @id' => [[['@id' => '{adminLineIri}']], 'jsonld'];
        yield '@id and own product' => [[['@id' => '{adminLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield '@id, own product and admin fields' => [[['@id' => '{adminLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0, 'quantityReceived' => 9.0, 'resellable' => true, 'receivedCondition' => 'tampered']], 'jsonld'];
        yield '@id and the foreign product' => [[['@id' => '{adminLineIri}', 'orderProduct' => '{foreignProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield '@id of a line that does not exist' => [[['@id' => '{missingLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield '@type and @id' => [[['@type' => 'OrderReturnLine', '@id' => '{adminLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield 'IRI string then a new line' => [['{adminLineIri}', ['orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield 'new line then an @id' => [[['orderProduct' => '{ownProduct}', 'quantity' => 1.0], ['@id' => '{adminLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield 'id in JSON-LD' => [[['id' => '{lineIdInt}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield 'id in JSON' => [[['id' => '{lineIdInt}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'json'];
        yield 'id as a string in JSON' => [[['id' => '{lineIdString}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'json'];
        yield '@id in JSON' => [[['@id' => '{adminLineIri}', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'json'];
        yield 'empty @id' => [[['@id' => '', 'orderProduct' => '{ownProduct}', 'quantity' => 1.0]], 'jsonld'];
        yield 'IRI string in JSON' => [['{adminLineIri}'], 'json'];
    }

    /**
     * @param list<mixed> $lines
     */
    #[DataProvider('existingLineForms')]
    public function testALineNamingAnExistingOneIsRefusedAndTheLineLeftAlone(array $lines, string $format): void
    {
        $owner = $this->factory->customer($this->factory->customerTitle());
        $foreignReturn = $this->returnWithLine($owner, 3.0);
        $foreignLine = OrderReturnLineQuery::create()->findOneByOrderReturnId($foreignReturn->getId(), $this->getPropelConnection());
        self::assertNotNull($foreignLine);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $ownProduct = $this->orderProductFor($order, 2.0);

        $before = $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId());

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => $this->resolve($lines, $foreignLine, $ownProduct),
            ],
            token: $this->authenticateAsCustomer($customer),
            format: $format,
        );

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(self::EXISTING_LINE_MESSAGE, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['detail'] ?? null);
        self::assertSame($before, $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId()));
        self::assertSame(0, OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->count($this->getPropelConnection()));
    }

    /**
     * The return a line belongs to is not part of what a customer writes: a
     * line naming another one is a new line of the return being opened.
     */
    public function testALineNamingAnotherReturnIsANewLineOfTheReturnBeingOpened(): void
    {
        $owner = $this->factory->customer($this->factory->customerTitle());
        $foreignReturn = $this->returnWithLine($owner, 3.0);
        $foreignLine = OrderReturnLineQuery::create()->findOneByOrderReturnId($foreignReturn->getId(), $this->getPropelConnection());
        self::assertNotNull($foreignLine);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $ownProduct = $this->orderProductFor($order, 2.0);

        $before = $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId());

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [[
                    'orderProduct' => '/api/front/account/order_products/'.$ownProduct->getId(),
                    'quantity' => 1.0,
                    'orderReturn' => '/api/admin/order_returns/'.$foreignReturn->getId(),
                ]],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($before, $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId()));

        $return = OrderReturnQuery::create()->findOneByCustomerId((int) $customer->getId(), $this->getPropelConnection());
        self::assertNotNull($return);
        $lines = OrderReturnLineQuery::create()->filterByOrderReturnId($return->getId())->find($this->getPropelConnection());
        self::assertCount(1, $lines);
        self::assertSame((int) $ownProduct->getId(), (int) $lines[0]->getOrderProductId());
        self::assertNotSame((int) $foreignLine->getId(), (int) $lines[0]->getId());
    }

    /**
     * A key set to null names nothing, for API Platform as for the denormalizer.
     */
    public function testALineWithANullIdIsANewLine(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $ownProduct = $this->orderProductFor($order, 2.0);

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [[
                    'id' => null,
                    'orderProduct' => '/api/front/account/order_products/'.$ownProduct->getId(),
                    'quantity' => 1.0,
                ]],
            ],
            token: $this->authenticateAsCustomer($customer),
            format: 'json',
        );

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $return = OrderReturnQuery::create()->findOneByCustomerId((int) $customer->getId(), $this->getPropelConnection());
        self::assertNotNull($return);
        $lines = OrderReturnLineQuery::create()->filterByOrderReturnId($return->getId())->find($this->getPropelConnection());
        self::assertCount(1, $lines);
        self::assertSame((int) $ownProduct->getId(), (int) $lines[0]->getOrderProductId());
    }

    /**
     * The order product of a line is not a way in either: given as an object
     * that carries an `@id`, it is not a plain IRI, and the foreign rows stay
     * as they were.
     */
    public function testAnOrderProductGivenAsAnObjectWithAnIdIsRefusedAndTheForeignLineLeftAlone(): void
    {
        $owner = $this->factory->customer($this->factory->customerTitle());
        $foreignReturn = $this->returnWithLine($owner, 3.0);
        $foreignLine = OrderReturnLineQuery::create()->findOneByOrderReturnId($foreignReturn->getId(), $this->getPropelConnection());
        self::assertNotNull($foreignLine);

        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->orderProductFor($order, 2.0);

        $before = $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId());

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [[
                    'orderProduct' => ['@id' => '/api/front/account/order_products/'.$foreignLine->getOrderProductId()],
                    'quantity' => 1.0,
                ]],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($before, $this->snapshot((int) $foreignLine->getId(), (int) $foreignReturn->getId()));
        self::assertSame(0, OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->count($this->getPropelConnection()));
    }

    /**
     * @param list<mixed> $lines
     *
     * @return list<mixed>
     */
    private function resolve(array $lines, OrderReturnLine $foreignLine, OrderProductModel $ownProduct): array
    {
        $replacements = [
            '{adminLineIri}' => '/api/admin/order_return_lines/'.$foreignLine->getId(),
            '{frontLineIri}' => '/api/front/account/order_return_lines/'.$foreignLine->getId(),
            '{missingLineIri}' => '/api/admin/order_return_lines/999999999',
            '{lineIdString}' => (string) $foreignLine->getId(),
            '{ownProduct}' => '/api/front/account/order_products/'.$ownProduct->getId(),
            '{foreignProduct}' => '/api/front/account/order_products/'.$foreignLine->getOrderProductId(),
        ];

        $resolve = static function (mixed $value) use (&$resolve, $replacements, $foreignLine): mixed {
            if (\is_array($value)) {
                return array_map($resolve, $value);
            }

            if ('{lineIdInt}' === $value) {
                return (int) $foreignLine->getId();
            }

            return \is_string($value) ? strtr($value, $replacements) : $value;
        };

        return $resolve($lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(int $lineId, int $returnId): array
    {
        OrderReturnLineTableMap::clearInstancePool();
        OrderReturnTableMap::clearInstancePool();

        $line = OrderReturnLineQuery::create()->findPk($lineId, $this->getPropelConnection());
        $return = OrderReturnQuery::create()->findPk($returnId, $this->getPropelConnection());

        return [
            'line' => $line?->toArray(),
            'return' => $return?->toArray(),
            'lineCount' => OrderReturnLineQuery::create()->filterByOrderReturnId($returnId)->count($this->getPropelConnection()),
        ];
    }

    private function returnWithLine(Customer $customer, float $quantity): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProductFor($order, $quantity);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED));
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId($orderProduct->getProductSaleElementsId())
            ->setQuantity($quantity)
            ->setQuantityReceived(0.0)
            ->save($this->getPropelConnection());

        return $return;
    }

    private function orderProductFor(Order $order, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}

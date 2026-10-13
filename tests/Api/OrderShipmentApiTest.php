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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The shipment API keeps every customer to the parcels of their own orders, gives
 * each parcel its carrier page, and lets an authorized administrator open, ship
 * and cancel parcels without ever exceeding what was ordered.
 */
final class OrderShipmentApiTest extends ApiTestCase
{
    private const CARRIER_CODE = 'OrderShipmentApiCarrier';

    private const TEMPLATE = 'https://carrier.example/track?parcel=%ID%';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testACustomerOnlySeesTheParcelsOfTheirOwnOrders(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        [$order, $line] = $this->paidOrderWithALine($owner, 2.0);
        $shipment = $this->shipped($order, $line, 1.0, 'PARCEL-1');

        $ownerToken = $this->authenticateAsCustomer($owner);
        $response = $this->jsonRequest('GET', '/api/front/account/order_shipments', token: $ownerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
        self::assertSame($shipment->getId(), $this->decodeMembers($response)[0]['id'] ?? null);

        $strangerToken = $this->authenticateAsCustomer($stranger);
        $response = $this->jsonRequest('GET', '/api/front/account/order_shipments', token: $strangerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(0, $response);

        // The owner reading the single parcel proves the route exists before the
        // stranger is refused on it.
        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', '/api/front/account/order_shipments/'.$shipment->getId(), token: $ownerToken)->getStatusCode());
        self::assertContains(
            $this->jsonRequest('GET', '/api/front/account/order_shipments/'.$shipment->getId(), token: $strangerToken)->getStatusCode(),
            [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
        );
    }

    public function testAParcelReadByItsOwnerCarriesItsLinesNumberAndCarrierPage(): void
    {
        $owner = $this->customer();
        [$order, $line] = $this->paidOrderWithALine($owner, 2.0, $this->carrier());
        $shipment = $this->shipped($order, $line, 1.0, '6A 12');

        $response = $this->jsonRequest('GET', '/api/front/account/order_shipments/'.$shipment->getId(), token: $this->authenticateAsCustomer($owner));
        self::assertJsonResponseSuccessful($response);
        $parcel = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame($order->getRef().'-1', $parcel['ref']);
        self::assertSame(OrderShipment::STATUS_SHIPPED, $parcel['status']);
        self::assertSame('6A 12', $parcel['deliveryRef']);
        self::assertSame('https://carrier.example/track?parcel=6A%2012', $parcel['trackingUrl']);
        self::assertNotNull($parcel['shippedAt']);
        self::assertCount(1, $parcel['orderShipmentItems']);
        self::assertSame(1.0, (float) $parcel['orderShipmentItems'][0]['quantity']);
        self::assertSame($line->getId(), $parcel['orderShipmentItems'][0]['orderProduct']['id'] ?? null);
    }

    public function testTheOrderReadByItsOwnerListsItsParcels(): void
    {
        $owner = $this->customer();
        [$order, $line] = $this->paidOrderWithALine($owner, 2.0);
        $this->shipped($order, $line, 1.0, 'PARCEL-1');
        $this->shipped($order, $line, 1.0, 'PARCEL-2');

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($owner));
        self::assertJsonResponseSuccessful($response);
        $read = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(['PARCEL-1', 'PARCEL-2'], array_column($read['orderShipments'], 'deliveryRef'));
        self::assertSame([$order->getRef().'-1', $order->getRef().'-2'], array_column($read['orderShipments'], 'ref'));
    }

    public function testAnAdministratorOpensAParcelThatLeavesAtOnce(): void
    {
        [$order, $line] = $this->paidOrderWithALine($this->customer(), 3.0);

        $response = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'deliveryRef' => 'ADMIN-1',
            'ship' => true,
            'orderShipmentItems' => [
                ['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 2],
            ],
        ], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $parcel = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(OrderShipment::STATUS_SHIPPED, $parcel['status']);
        self::assertSame('ADMIN-1', $parcel['deliveryRef']);
        self::assertSame(2.0, (float) $parcel['orderShipmentItems'][0]['quantity']);

        $order->reload();
        self::assertSame(OrderStatus::CODE_PARTIALLY_SHIPPED, OrderStatusQuery::create()->findPk($order->getStatusId())?->getCode());
        self::assertSame('ADMIN-1', $order->getDeliveryRef());
    }

    public function testAParcelBeyondWhatIsLeftToShipIsRefused(): void
    {
        [$order, $line] = $this->paidOrderWithALine($this->customer(), 3.0);
        $this->shipped($order, $line, 2.0, 'PARCEL-1');

        $response = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [
                ['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 2],
            ],
        ], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, OrderShipmentQuery::create()->filterByOrderId($order->getId())->find());
    }

    public function testADraftIsShippedThenRefusedAndCancelledThroughTheTransition(): void
    {
        [$order, $line] = $this->paidOrderWithALine($this->customer(), 3.0);
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [
                ['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 1],
            ],
        ], token: $token);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $draftId = json_decode((string) $created->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        $cancelled = $this->jsonRequest('POST', '/api/admin/order_shipments/'.$draftId.'/transition', ['action' => 'cancel'], token: $token);
        self::assertSame(Response::HTTP_OK, $cancelled->getStatusCode(), (string) $cancelled->getContent());
        self::assertSame(OrderShipment::STATUS_CANCELLED, json_decode((string) $cancelled->getContent(), true, 512, \JSON_THROW_ON_ERROR)['status']);

        $again = $this->jsonRequest('POST', '/api/admin/order_shipments/'.$draftId.'/transition', ['action' => 'ship'], token: $token);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $again->getStatusCode());

        $second = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [
                ['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 3],
            ],
        ], token: $token);
        $secondId = json_decode((string) $second->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        $shipped = $this->jsonRequest('POST', '/api/admin/order_shipments/'.$secondId.'/transition', ['action' => 'ship', 'deliveryRef' => 'LATE-NUMBER'], token: $token);
        self::assertSame(Response::HTTP_OK, $shipped->getStatusCode(), (string) $shipped->getContent());
        $parcel = json_decode((string) $shipped->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(OrderShipment::STATUS_SHIPPED, $parcel['status']);
        self::assertSame('LATE-NUMBER', $parcel['deliveryRef']);

        $order->reload();
        self::assertSame(OrderStatus::CODE_SENT, OrderStatusQuery::create()->findPk($order->getStatusId())?->getCode());
    }

    public function testAnAdministratorWithoutTheOrderRightCannotOpenAParcel(): void
    {
        [$order, $line] = $this->paidOrderWithALine($this->customer(), 3.0);
        $token = $this->authenticateAsAdmin($this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]));

        $response = $this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [
                ['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 1],
            ],
        ], token: $token);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', '/api/admin/order_shipments', token: $token)->getStatusCode(), 'The view right still reads the parcels.');
    }

    public function testACustomerTokenIsRefusedOnTheAdministrationOperations(): void
    {
        [$order, $line] = $this->paidOrderWithALine($this->customer(), 3.0);
        $token = $this->authenticateAsCustomer($this->customer());

        self::assertContains($this->jsonRequest('GET', '/api/admin/order_shipments', token: $token)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
        self::assertContains($this->jsonRequest('POST', '/api/admin/order_shipments', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderShipmentItems' => [['orderProduct' => '/api/admin/order_products/'.$line->getId(), 'quantity' => 1]],
        ], token: $token)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * @return array{0: Order, 1: OrderProduct}
     */
    private function paidOrderWithALine(Customer $customer, float $quantity, ?Module $carrier = null): array
    {
        $overrides = ['statusCode' => OrderStatus::CODE_PAID];

        if (null !== $carrier) {
            $overrides['deliveryModuleCode'] = $carrier->getCode();
        }

        $order = $this->factory->order($customer, $overrides);

        return [$order, $this->factory->orderProduct($order, ['quantity' => $quantity])];
    }

    private function shipped(Order $order, OrderProduct $line, float $quantity, string $deliveryRef): OrderShipment
    {
        $manager = $this->getService(OrderShipmentManager::class);

        return $manager->ship($manager->open($order, [new ShipmentLine((int) $line->getId(), $quantity)], deliveryRef: $deliveryRef));
    }

    private function carrier(): Module
    {
        $existing = ModuleQuery::create()->filterByCode(self::CARRIER_CODE)->findOne($this->getPropelConnection());

        if (null !== $existing) {
            return $existing;
        }

        $module = new Module();
        $module
            ->setCode(self::CARRIER_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::CARRIER_CODE.'\\'.self::CARRIER_CODE)
            ->save($this->getPropelConnection());
        ModuleConfigQuery::create()->setConfigValue($module->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, self::TEMPLATE);

        return $module;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeMembers(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        return $decoded['member'] ?? $decoded['hydra:member'] ?? [];
    }
}

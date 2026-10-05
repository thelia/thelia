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

use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;

/**
 * Payload coverage for /api/front/account/orders/{id}.
 *
 * A theme rendering the order page reads the whole order from this single
 * operation, so the virtual flags of each line have to travel with it.
 */
final class AccountOrderApiTest extends ApiTestCase
{
    private const string CARRIER_CODE = 'AccountOrderApiTestCarrier';

    protected function tearDown(): void
    {
        // The module configuration is memoized in a static cache that outlives the
        // transaction rollback.
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testOrderPayloadExposesTheVirtualFlagsOfItsProducts(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer, ['statusCode' => 'paid']);

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('REF-VIRTUAL')
            ->setProductSaleElementsRef('REF-VIRTUAL-PSE')
            ->setTitle('Virtual product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setPromoPrice('0.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(1)
            ->setVirtualDocument('user-guide.pdf')
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data['orderProducts']);
        self::assertTrue($data['orderProducts'][0]['virtual']);
        self::assertSame('user-guide.pdf', $data['orderProducts'][0]['virtualDocument']);
    }

    public function testOrderPayloadExposesItsFrozenVatExemptionState(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer, ['statusCode' => 'paid']);

        $order->getOrderAddressRelatedByInvoiceOrderAddressId()
            ->setVatExempted(1)
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-10 days'))
            ->setVatVerifiedName('Acme SPRL')
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['vatExempted']);
        self::assertSame('Acme SPRL', $data['invoiceOrderAddress']['vatVerifiedName']);
    }

    public function testOrderPayloadGivesTheCarrierPageFollowingTheParcel(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $this->sentOrder($customer, 'https://carrier.example/track?parcel=%ID%', '6A 12');

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        self::assertSame('6A 12', $data['deliveryRef']);
        self::assertSame('https://carrier.example/track?parcel=6A%2012', $data['deliveryTrackingUrl']);
    }

    public function testOrderPayloadHasNoTrackingLinkWithoutTrackingNumber(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $this->sentOrder($customer, 'https://carrier.example/track?parcel=%ID%', null);

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        self::assertArrayNotHasKey('deliveryTrackingUrl', json_decode($response->getContent(), true), 'An order without a tracking number has no link to give.');
    }

    public function testTheTrackingLinkOfAnOrderIsNotGivenToAnotherCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $owner = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $stranger = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $this->sentOrder($owner, 'https://carrier.example/track?parcel=%ID%', '6A12');

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($stranger));

        self::assertContains($response->getStatusCode(), [403, 404]);
        self::assertStringNotContainsString('carrier.example', (string) $response->getContent());
    }

    private function sentOrder(Customer $customer, string $trackingUrlTemplate, ?string $trackingNumber): Order
    {
        $carrier = new Module();
        $carrier
            ->setCode(self::CARRIER_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::CARRIER_CODE.'\\'.self::CARRIER_CODE)
            ->save($this->getPropelConnection());
        ModuleConfigQuery::create()->setConfigValue($carrier->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, $trackingUrlTemplate);

        $order = $this->createFixtureFactory()->order($customer, ['statusCode' => 'sent', 'deliveryModuleCode' => self::CARRIER_CODE]);
        $order->setDeliveryRef($trackingNumber)->save($this->getPropelConnection());

        return $order;
    }
}

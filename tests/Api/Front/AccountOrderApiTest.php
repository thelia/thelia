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

use Thelia\Model\OrderProduct;
use Thelia\Test\ApiTestCase;

/**
 * Payload coverage for /api/front/account/orders/{id}.
 *
 * A theme rendering the order page reads the whole order from this single
 * operation, so the virtual flags of each line have to travel with it.
 */
final class AccountOrderApiTest extends ApiTestCase
{
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
}

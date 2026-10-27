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

use Thelia\Test\ApiTestCase;

/**
 * The group frozen on an order is shown to its owner only.
 */
final class AccountOrderCustomerGroupApiTest extends ApiTestCase
{
    public function testOwnerReadsTheFrozenGroupAndRate(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('front-pro', 20.0, false);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer, ['statusCode' => 'paid']);
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('20.000000')->setCustomerGroupDisplayTaxedPrice(0)->save();

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($group->getId(), $data['customerGroupId']);
        self::assertSame('front-pro', $data['customerGroupCode']);
        self::assertEquals(20.0, $data['customerGroupDiscountRate']);
        self::assertFalse($data['customerGroupDisplayTaxedPrice']);
    }

    public function testAnotherCustomerCannotReadTheGroupOfSomeoneElsesOrder(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('front-secret', 20.0);
        $owner = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $intruder = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($owner, ['statusCode' => 'paid']);
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('20.000000')->save();

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($intruder));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('front-secret', (string) $response->getContent());

        $list = $this->jsonRequest('GET', '/api/front/account/orders', token: $this->authenticateAsCustomer($intruder));
        self::assertStringNotContainsString('front-secret', (string) $list->getContent());
    }
}

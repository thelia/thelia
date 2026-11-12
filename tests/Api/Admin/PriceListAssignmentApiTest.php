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

namespace Thelia\Tests\Api\Admin;

use Thelia\Model\PriceListAssignmentQuery;

final class PriceListAssignmentApiTest extends PriceListApiTestCase
{
    public function testAListIsAssignedToAGroupAndToACustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $list = $this->priceList();
        $group = $factory->customerGroup();
        $customer = $factory->customer($factory->customerTitle());
        $token = $this->pricingAdminToken();

        $byGroup = $this->decode($this->jsonRequest('POST', '/api/admin/price_list_assignments', ['priceList' => '/api/admin/price_lists/'.$list->getId(), 'customerGroup' => '/api/admin/customer_groups/'.$group->getId()], $token), 201);
        $byCustomer = $this->decode($this->jsonRequest('POST', '/api/admin/price_list_assignments', ['priceList' => '/api/admin/price_lists/'.$list->getId(), 'customer' => '/api/admin/customers/'.$customer->getId()], $token), 201);

        $groupRow = PriceListAssignmentQuery::create()->findPk($byGroup['id']);
        self::assertSame($group->getId(), $groupRow->getCustomerGroupId());
        self::assertNull($groupRow->getCustomerId());
        $customerRow = PriceListAssignmentQuery::create()->findPk($byCustomer['id']);
        self::assertSame($customer->getId(), $customerRow->getCustomerId());
        self::assertNull($customerRow->getCustomerGroupId());

        $read = $this->decode($this->jsonRequest('GET', '/api/admin/price_list_assignments/'.$byGroup['id'], token: $token));
        self::assertArrayNotHasKey('customer', $read, 'a scope the assignment does not have is not read');

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/price_list_assignments/'.$byGroup['id'], token: $token)->getStatusCode());
        self::assertNull(PriceListAssignmentQuery::create()->findPk($byGroup['id']));
    }

    public function testAnAssignmentToBothAGroupAndACustomerOrToNobodyIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $list = $this->priceList();
        $group = $factory->customerGroup();
        $customer = $factory->customer($factory->customerTitle());
        $token = $this->pricingAdminToken();

        $both = $this->jsonRequest('POST', '/api/admin/price_list_assignments', [
            'priceList' => '/api/admin/price_lists/'.$list->getId(),
            'customerGroup' => '/api/admin/customer_groups/'.$group->getId(),
            'customer' => '/api/admin/customers/'.$customer->getId(),
        ], $token);
        self::assertContains('customer', $this->violationPaths($both));

        $nobody = $this->jsonRequest('POST', '/api/admin/price_list_assignments', ['priceList' => '/api/admin/price_lists/'.$list->getId()], $token);
        self::assertContains('customerGroup', $this->violationPaths($nobody));

        self::assertSame(0, PriceListAssignmentQuery::create()->filterByPriceListId($list->getId())->count());
    }

    public function testASecondAssignmentToTheSameGroupOrCustomerIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $list = $this->priceList();
        $group = $factory->customerGroup();
        $customer = $factory->customer($factory->customerTitle());
        $token = $this->pricingAdminToken();
        $byGroup = ['priceList' => '/api/admin/price_lists/'.$list->getId(), 'customerGroup' => '/api/admin/customer_groups/'.$group->getId()];
        $byCustomer = ['priceList' => '/api/admin/price_lists/'.$list->getId(), 'customer' => '/api/admin/customers/'.$customer->getId()];
        $this->decode($this->jsonRequest('POST', '/api/admin/price_list_assignments', $byGroup, $token), 201);
        $this->decode($this->jsonRequest('POST', '/api/admin/price_list_assignments', $byCustomer, $token), 201);

        self::assertContains('customerGroup', $this->violationPaths($this->jsonRequest('POST', '/api/admin/price_list_assignments', $byGroup, $token)));
        self::assertContains('customer', $this->violationPaths($this->jsonRequest('POST', '/api/admin/price_list_assignments', $byCustomer, $token)));
        self::assertSame(2, PriceListAssignmentQuery::create()->filterByPriceListId($list->getId())->count());
    }

    public function testAProfileWithoutThePricingRightCannotSeeWhoIsOnWhichList(): void
    {
        $factory = $this->createFixtureFactory();
        $list = $this->priceList();
        $group = $factory->customerGroup();
        $token = $this->catalogOnlyAdminToken();
        $payload = ['priceList' => '/api/admin/price_lists/'.$list->getId(), 'customerGroup' => '/api/admin/customer_groups/'.$group->getId()];

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/price_list_assignments', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/price_list_assignments', $payload, $token)->getStatusCode());
        self::assertSame(0, PriceListAssignmentQuery::create()->filterByPriceListId($list->getId())->count());
    }
}

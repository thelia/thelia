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

use Thelia\Model\Sale;
use Thelia\Test\ApiTestCase;

/**
 * The customer groups an operation is reserved for are read by an administrator on the
 * single read of the operation, and never by the front.
 */
final class SaleCustomerGroupApiTest extends ApiTestCase
{
    public function testAnOperationReservedForGroupsIsReadWithItsGroupsByAnAdministratorOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleCustomerGroup($sale, $group);

        $admin = $this->jsonRequest('GET', '/api/admin/sales/'.$sale->getId(), token: $this->authenticateAsAdmin());
        self::assertJsonResponseSuccessful($admin);
        self::assertSame([$group->getId()], json_decode((string) $admin->getContent(), true, flags: \JSON_THROW_ON_ERROR)['customerGroupIds']);

        $front = $this->jsonRequest('GET', '/api/front/sales/'.$sale->getId(), token: $this->authenticateAsCustomer($factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $group])));
        self::assertJsonResponseSuccessful($front);
        self::assertStringNotContainsString('customerGroupIds', (string) $front->getContent());
    }
}

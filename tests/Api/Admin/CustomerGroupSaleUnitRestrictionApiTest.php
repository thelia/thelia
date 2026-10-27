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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The back office keeps the customer groups, their prices and the group of a customer away from an
 * administrator restricted to a sale unit: a group price or a move to another group changes what
 * every unit sells. The API does the same: 403 on every write, and the reads stay open.
 */
final class CustomerGroupSaleUnitRestrictionApiTest extends ApiTestCase
{
    private const array ALL = [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE];

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testARestrictedAdministratorReadsTheGroupsAndWritesNone(): void
    {
        $group = $this->factory->customerGroup('restricted-reads');
        $token = $this->authenticateAsAdmin($this->restrictedAdmin());

        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/customer_groups', token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/customer_groups/'.$group->getId(), token: $token)->getStatusCode());

        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'restricted-new', 'title' => 'New'], $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PUT', '/api/admin/customer_groups/'.$group->getId(), ['code' => 'restricted-reads'], $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$group->getId(), ['discount' => 40.0], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/customer_groups/'.$group->getId(), token: $token)->getStatusCode());

        $this->groupAfter($group);
        self::assertSame(0.0, (float) $this->groupAfter($group)->getDiscount());
        self::assertNull(CustomerGroupQuery::create()->findOneByCode('restricted-new'));
    }

    public function testAnUnrestrictedAdministratorStillWritesTheGroups(): void
    {
        $group = $this->factory->customerGroup('unrestricted-writes');
        $token = $this->authenticateAsAdmin($this->profiledAdmin());

        self::assertSame(201, $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'unrestricted-new', 'title' => 'New'], $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$group->getId(), ['discount' => 40.0], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(40.0, (float) $this->groupAfter($group)->getDiscount());
    }

    public function testARestrictedAdministratorReadsTheGroupPricesAndWritesNone(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup('restricted-prices');
        $price = $this->factory->customerGroupProductPrice($group, $pse, $currency, 42.5);
        $token = $this->authenticateAsAdmin($this->restrictedAdmin());

        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/customer_group_product_prices', token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/customer_group_product_prices/'.$price->getId(), token: $token)->getStatusCode());

        $creation = [
            'customerGroup' => '/api/admin/customer_groups/'.$group->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$pse->getId(),
            'currency' => '/api/admin/currencies/'.$currency->getId(),
            'price' => 10.0,
        ];
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customer_group_product_prices', $creation, $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PUT', '/api/admin/customer_group_product_prices/'.$price->getId(), $creation, $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/admin/customer_group_product_prices/'.$price->getId(), ['price' => 1.0], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/customer_group_product_prices/'.$price->getId(), token: $token)->getStatusCode());

        self::assertSame(42.5, (float) CustomerGroupProductPriceQuery::create()->findPk($price->getId())?->getPrice());
    }

    public function testARestrictedAdministratorCannotMoveACustomerToAnotherGroupButWritesTheRest(): void
    {
        $other = $this->factory->customerGroup('restricted-move');
        $customer = $this->factory->customer($this->factory->customerTitle());
        $current = $customer->getCustomerGroupId();
        $token = $this->authenticateAsAdmin($this->restrictedAdmin());
        $uri = '/api/admin/customers/'.$customer->getId();

        $moved = $this->jsonRequest('PATCH', $uri, ['customerGroup' => '/api/admin/customer_groups/'.$other->getId()], $token, 'merge-patch+json');
        self::assertSame(403, $moved->getStatusCode(), (string) $moved->getContent());
        self::assertSame($current, CustomerQuery::create()->findPk($customer->getId())?->getCustomerGroupId());

        $repeated = $this->jsonRequest('PATCH', $uri, ['customerGroup' => '/api/admin/customer_groups/'.$current, 'firstname' => 'Renamed'], $token, 'merge-patch+json');
        self::assertSame(200, $repeated->getStatusCode(), 'the group it already has is no move: '.$repeated->getContent());

        $renamed = $this->jsonRequest('PATCH', $uri, ['lastname' => 'Changed'], $token, 'merge-patch+json');
        self::assertSame(200, $renamed->getStatusCode(), (string) $renamed->getContent());
        self::assertSame('Changed', CustomerQuery::create()->findPk($customer->getId())?->getLastname());
        self::assertSame($current, CustomerQuery::create()->findPk($customer->getId())?->getCustomerGroupId());
    }

    public function testARestrictedAdministratorCannotCreateACustomerInAnotherGroup(): void
    {
        $other = $this->factory->customerGroup('restricted-create');
        $title = $this->factory->customerTitle();
        $token = $this->authenticateAsAdmin($this->restrictedAdmin());

        $body = [
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'password' => 'Password1!',
        ];

        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customers', $body + ['email' => 'restricted-group@example.com', 'customerGroup' => '/api/admin/customer_groups/'.$other->getId()], $token)->getStatusCode());
        self::assertNull(CustomerQuery::create()->findOneByEmail('restricted-group@example.com'));

        $created = $this->jsonRequest('POST', '/api/admin/customers', $body + ['email' => 'restricted-default@example.com'], $token);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
    }

    public function testAnUnrestrictedAdministratorMovesACustomerToAnotherGroup(): void
    {
        $other = $this->factory->customerGroup('unrestricted-move');
        $customer = $this->factory->customer($this->factory->customerTitle());
        $token = $this->authenticateAsAdmin($this->profiledAdmin());

        $moved = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customer->getId(), ['customerGroup' => '/api/admin/customer_groups/'.$other->getId()], $token, 'merge-patch+json');

        self::assertSame(200, $moved->getStatusCode(), (string) $moved->getContent());
        self::assertSame($other->getId(), CustomerQuery::create()->findPk($customer->getId())?->getCustomerGroupId());
    }

    private function groupAfter(CustomerGroup $group): CustomerGroup
    {
        return CustomerGroupQuery::create()->findPk($group->getId()) ?? throw new \LogicException('The group is gone.');
    }

    private function profiledAdmin(): Admin
    {
        return $this->factory->restrictedAdmin([
            AdminResources::CUSTOMER => self::ALL,
            AdminResources::CUSTOMER_GROUP => self::ALL,
        ]);
    }

    private function restrictedAdmin(): Admin
    {
        $admin = $this->profiledAdmin();
        $this->factory->adminSaleUnit($admin, $this->factory->saleUnit());

        return $admin;
    }
}

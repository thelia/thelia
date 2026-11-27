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

namespace Thelia\Tests\Unit\Test\Volume;

use PHPUnit\Framework\TestCase;
use Thelia\Test\Volume\Measure\MeasurePlan;

final class MeasurePlanTest extends TestCase
{
    public function testThePlaceholdersAreReadFromTheDataset(): void
    {
        $plan = MeasurePlan::fromArray([
            'sign_in' => ['customer' => ['form' => '/customer/login', 'action' => '/customer/login', 'fields' => ['email' => '{customer_email}']]],
            'pages' => [
                ['name' => 'product', 'path' => '{product_url}'],
                ['name' => 'orders', 'area' => 'account', 'path' => '/account/orders', 'session' => 'customer'],
            ],
            'jobs' => [['name' => 'export', 'command' => ['export', '{export_ref}']]],
        ], ['customer_email' => 'volume-customer-1@example.com', 'product_url' => '/oak-chair-1.html', 'export_ref' => 'thelia.export.orders']);

        self::assertSame(['email' => 'volume-customer-1@example.com'], $plan->signIns['customer']['fields']);
        self::assertSame(['name' => 'product', 'area' => 'front', 'path' => '/oak-chair-1.html', 'session' => 'anonymous', 'submit' => null], $plan->pages[0]);
        self::assertSame('customer', $plan->pages[1]['session']);
        self::assertSame(['export', 'thelia.export.orders'], $plan->jobs[0]['command']);
    }

    public function testASubmitEntryKeepsItsActionFieldsAndExpectedRedirect(): void
    {
        $plan = MeasurePlan::fromArray([
            'sign_in' => ['admin' => ['form' => '/admin/login', 'action' => '/admin/checklogin']],
            'pages' => [[
                'name' => 'admin-product-save',
                'path' => '/admin/products/update?product_id={product_id}',
                'session' => 'admin',
                'submit' => ['action' => '/admin/products/save', 'fields' => ['save_mode' => 'close'], 'expect' => '/admin/products'],
            ]],
        ], ['product_id' => 42]);

        self::assertSame('/admin/products/update?product_id=42', $plan->pages[0]['path']);
        self::assertSame(['action' => '/admin/products/save', 'fields' => ['save_mode' => 'close'], 'expect' => '/admin/products'], $plan->pages[0]['submit']);
    }

    public function testAPlaceholderTheDatasetDoesNotGiveIsRefused(): void
    {
        $this->expectExceptionMessage('{missing}');

        MeasurePlan::fromArray(['pages' => [['name' => 'page', 'path' => '/{missing}']]], []);
    }

    public function testAPageOfASessionThePlanDoesNotSignInIsRefused(): void
    {
        $this->expectExceptionMessage('the session admin');

        MeasurePlan::fromArray(['pages' => [['name' => 'admin', 'path' => '/admin', 'session' => 'admin']]], []);
    }

    public function testTheDefaultPlanOfTheRepositoryResolvesWithTheDatasetPlaceholders(): void
    {
        $plan = MeasurePlan::fromFile(\dirname(__DIR__, 4).'/setup/volume/pages.yaml', [
            'product_url' => '/p.html', 'product_id' => 1, 'category_url' => '/c.html', 'category_id' => 1, 'order_id' => 1,
            'customer_id' => 1, 'customer_email' => 'c@example.com', 'customer_password' => 'p', 'admin_login' => 'a',
            'admin_password' => 'p', 'admin_products_last_page' => 2, 'admin_orders_last_page' => 2, 'admin_customers_last_page' => 2,
        ]);

        self::assertGreaterThanOrEqual(10, \count($plan->pages));
        self::assertNotEmpty(array_filter($plan->pages, static fn (array $page): bool => null !== $page['submit']));
        self::assertSame(['customer', 'admin'], array_keys($plan->signIns));
    }
}

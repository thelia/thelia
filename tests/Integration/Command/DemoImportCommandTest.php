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

namespace Thelia\Tests\Integration\Command;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\NewsletterQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;

final class DemoImportCommandTest extends IntegrationTestCase
{
    public function testImportSeedsCatalogCustomersAndOrders(): void
    {
        $this->runImport();

        self::assertGreaterThan(0, ProductQuery::create()->count(), 'products');
        self::assertGreaterThan(0, BrandQuery::create()->count(), 'brands');
        self::assertGreaterThan(0, CategoryQuery::create()->count(), 'categories');
        self::assertGreaterThan(
            0,
            CategoryQuery::create()->filterByParent(0, Criteria::GREATER_THAN)->count(),
            'category tree has sub-categories',
        );

        self::assertGreaterThan(1, CustomerQuery::create()->count(), 'multiple customers');
        self::assertGreaterThan(0, NewsletterQuery::create()->count(), 'newsletter subscribers');
        self::assertGreaterThan(0, CouponQuery::create()->count(), 'coupons');

        self::assertGreaterThan(0, OrderQuery::create()->count(), 'orders');
        self::assertGreaterThan(0, OrderProductQuery::create()->count(), 'order products');

        $order = OrderQuery::create()->findOne();
        self::assertNotNull($order);
        self::assertGreaterThan(0.0, $order->getTotalAmount(), 'order total is computed from its products');
    }

    public function testReimportIsIdempotent(): void
    {
        $this->runImport();
        $firstProducts = ProductQuery::create()->count();
        $firstOrders = OrderQuery::create()->count();

        $this->runImport();

        self::assertSame($firstProducts, ProductQuery::create()->count(), 'product count is stable across reruns');
        self::assertSame($firstOrders, OrderQuery::create()->count(), 'order count is stable across reruns');
    }

    private function runImport(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:demo:import'),
        );
        $tester->execute(['--reset' => true, '--skip-images' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }
}

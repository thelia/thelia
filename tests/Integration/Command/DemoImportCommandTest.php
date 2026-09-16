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
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
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
        self::assertSame(
            0,
            CustomerQuery::create()->filterByLangId(null, Criteria::ISNULL)->count(),
            'every demo customer carries a language',
        );
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

    /**
     * The point of the demo data is that the shop can be walked end to end. Without a
     * carrier the walk stops at the delivery step with "No delivery method is available
     * for this order", one click short of the order.
     */
    public function testImportGivesTheDemoShopACarrier(): void
    {
        $this->runImport();

        $module = ModuleQuery::create()->findOneByCode('CustomDelivery');
        self::assertNotNull($module, 'CustomDelivery ships with the themes and is activated by the install');

        $franceAreaId = 1;

        self::assertNotNull(
            AreaDeliveryModuleQuery::create()
                ->filterByAreaId($franceAreaId)
                ->filterByDeliveryModuleId($module->getId())
                ->findOne(),
            'the demo shipping zone is served by a delivery module',
        );

        // The slice table belongs to the module, so it is read through SQL. A slice whose
        // bounds sit at zero — the column default — is matched by nothing: the demo needs
        // bounds above any cart it can build.
        $statement = $this->getPropelConnection()->prepare(
            'SELECT price, price_max, weight_max FROM custom_delivery_slice WHERE area_id = :areaId'
        );
        $statement->execute(['areaId' => $franceAreaId]);
        $slice = $statement->fetch(\PDO::FETCH_ASSOC);

        self::assertIsArray($slice, 'the demo shipping zone carries a price slice');
        self::assertGreaterThan(0.0, (float) $slice['price'], 'the slice quotes a price');
        self::assertGreaterThan(1000.0, (float) $slice['price_max'], 'the slice covers any demo cart total');
        self::assertGreaterThan(1000.0, (float) $slice['weight_max'], 'the slice covers any demo cart weight');
    }

    public function testReimportDoesNotDuplicateTheDemoCarrier(): void
    {
        $this->runImport();
        $firstAttachments = AreaDeliveryModuleQuery::create()->count();

        $this->runImport();

        self::assertSame($firstAttachments, AreaDeliveryModuleQuery::create()->count(), 'carrier attachments are stable across reruns');
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

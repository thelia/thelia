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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\AreaQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Gives the demo shop a carrier.
 *
 * Without it the checkout stops at the delivery step: the shipping zones the base
 * install seeds carry no delivery module, and CustomDelivery — which the themes pull
 * in and which the install activates — has no price slice to quote. The demo exists so
 * that a shop can be walked end to end, and the walk stopped one step before the order.
 *
 * CustomDelivery is a module, so the core cannot depend on its classes: the module is
 * looked up by code and its slice table is written through SQL. When the module is not
 * installed the importer says so and moves on — the rest of the demo does not need it.
 */
final class DeliveryImporter extends AbstractDemoImporter
{
    private const MODULE_CODE = 'CustomDelivery';

    private const SLICE_TABLE = 'custom_delivery_slice';

    /**
     * One flat rate, quoted whatever the cart holds. The slice is matched on
     * "strictly greater than", so a bound at zero — the column default — never
     * matches anything: these are set well above any demo cart.
     */
    private const SLICE_PRICE = 9.9;

    private const SLICE_PRICE_MAX = 1000000.0;

    private const SLICE_WEIGHT_MAX = 1000000.0;

    public function priority(): int
    {
        // After the customers (100), before the orders (120), which resolve the same
        // delivery module to attach to the orders they create.
        return 115;
    }

    public function description(): string
    {
        return 'Delivery';
    }

    public function import(DemoImportContext $context): void
    {
        $module = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByCode(self::MODULE_CODE)
            ->findOne($context->connection);

        if (null === $module) {
            $context->output->writeln(
                '<comment>'.self::MODULE_CODE.' is not installed — the demo shop is left without a carrier</comment>'
            );

            return;
        }

        $moduleId = (int) $module->getId();
        $areaIds = $this->deliverableAreaIds($context);

        foreach ($areaIds as $areaId) {
            $this->attachModuleToArea($context, $areaId, $moduleId);
        }

        $this->createSlices($context, $areaIds);

        $context->output->writeln(
            \sprintf('  %s serving %d shipping zone(s)', self::MODULE_CODE, \count($areaIds))
        );
    }

    /**
     * Shipping zones that hold at least one country. A zone with no country can never be
     * matched at the delivery step, and offering a carrier there would only clutter the
     * back-office.
     *
     * @return list<int>
     */
    private function deliverableAreaIds(DemoImportContext $context): array
    {
        $ids = AreaQuery::create()
            ->useCountryAreaQuery()
            ->endUse()
            ->groupById()
            ->select(['Id'])
            ->find($context->connection)
            ->toArray();

        return array_map(intval(...), array_values($ids));
    }

    private function attachModuleToArea(DemoImportContext $context, int $areaId, int $moduleId): void
    {
        $existing = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($areaId)
            ->filterByDeliveryModuleId($moduleId)
            ->findOne($context->connection);

        if (null !== $existing) {
            return;
        }

        (new AreaDeliveryModule())
            ->setAreaId($areaId)
            ->setDeliveryModuleId($moduleId)
            ->save($context->connection);
    }

    /**
     * custom_delivery_slice belongs to the module, not to the core, so it has no model
     * here. The table is only there once the module schema has been applied.
     *
     * @param list<int> $areaIds
     */
    private function createSlices(DemoImportContext $context, array $areaIds): void
    {
        if (!$this->sliceTableExists($context)) {
            $context->output->writeln(
                '<comment>'.self::SLICE_TABLE.' is missing — no delivery price was created</comment>'
            );

            return;
        }

        $insert = $context->connection->prepare(
            'INSERT INTO `'.self::SLICE_TABLE.'` (`area_id`, `price_max`, `weight_max`, `price`)
             SELECT :areaId, :priceMax, :weightMax, :price FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM `'.self::SLICE_TABLE.'` WHERE `area_id` = :existingAreaId)'
        );

        foreach ($areaIds as $areaId) {
            $insert->execute([
                'areaId' => $areaId,
                'priceMax' => self::SLICE_PRICE_MAX,
                'weightMax' => self::SLICE_WEIGHT_MAX,
                'price' => self::SLICE_PRICE,
                'existingAreaId' => $areaId,
            ]);
        }
    }

    private function sliceTableExists(DemoImportContext $context): bool
    {
        $statement = $context->connection->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => self::SLICE_TABLE]);

        return false !== $statement->fetchColumn();
    }
}

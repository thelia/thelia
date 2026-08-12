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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleDefinition;
use Thelia\Test\ActionIntegrationTestCase;

final class ModuleActionTest extends ActionIntegrationTestCase
{
    public function testUpdateChangesI18nFields(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        $event = new ModuleEvent($module);
        $event->setId($module->getId());
        $event->setLocale('en_US');
        $event->setTitle('Cheque Updated');
        $event->setChapo('A short chapo');
        $event->setDescription('A module description');
        $event->setPostscriptum('PS text');

        $this->dispatch($event, TheliaEvents::MODULE_UPDATE);

        $reloaded = ModuleQuery::create()->findPk($module->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Cheque Updated', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('A short chapo', $reloaded->getChapo());
        self::assertSame('A module description', $reloaded->getDescription());
        self::assertSame('PS text', $reloaded->getPostscriptum());
    }

    public function testUpdatePositionMovesModuleToAbsolutePosition(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module);

        $targetPosition = $module->getPosition() + 1;

        $event = new UpdatePositionEvent(
            $module->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            $targetPosition,
        );

        $this->dispatch($event, TheliaEvents::MODULE_UPDATE_POSITION);

        self::assertSame(
            $targetPosition,
            ModuleQuery::create()->findPk($module->getId())->getPosition(),
        );
    }

    /**
     * Installing a newer version of an already installed module deactivates the current one before
     * replacing it. When the replacement cannot happen — an order was placed with this payment
     * module, so its row cannot be deleted — the shop must not be left with the module switched off.
     */
    public function testFailedUpgradeLeavesTheModuleActivated(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        $module->setActivate(BaseModule::IS_ACTIVATED)->save();

        // References Cheque as its payment module, which is what blocks the deletion.
        $this->factory->order();

        $definition = new ModuleDefinition();
        $definition->setCode((string) $module->getCode());
        $definition->setNamespace((string) $module->getFullNamespace());
        $definition->setVersion('99.0.0');

        $event = new ModuleInstallEvent();
        $event->setModuleDefinition($definition);
        $event->setModulePath((string) $module->getAbsoluteBaseDir());

        try {
            $this->dispatch($event, TheliaEvents::MODULE_INSTALL);
            self::fail('Installing over a module an order was placed with should have been refused.');
        } catch (\Exception $exception) {
            self::assertStringContainsString('in use by at least one order', $exception->getMessage());
        }

        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())->getActivate(),
            'A refused upgrade must leave the module activated, not silently switched off.',
        );
    }
}

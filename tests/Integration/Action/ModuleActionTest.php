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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ModuleQuery;
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
}

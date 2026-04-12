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

use Thelia\Core\Event\Hook\HookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookDeleteEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ModuleHookActionTest extends ActionIntegrationTestCase
{
    private function createTestHook(string $code): \Thelia\Model\Hook
    {
        $event = (new HookCreateEvent())
            ->setLocale('en_US')
            ->setCode($code)
            ->setType(TemplateDefinition::FRONT_OFFICE)
            ->setNative(false)
            ->setActive(true)
            ->setTitle('Hook '.$code);

        return $this->dispatch($event, TheliaEvents::HOOK_CREATE)->getHook();
    }

    private function getActiveModuleId(): int
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        return $module->getId();
    }

    public function testCreateModuleHookPersistsBinding(): void
    {
        $hook = $this->createTestHook('test.mh.create');
        $moduleId = $this->getActiveModuleId();

        $event = new ModuleHookCreateEvent();
        $event
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onMainFooter')
            ->setTemplates('');

        $this->dispatch($event, TheliaEvents::MODULE_HOOK_CREATE);

        $moduleHook = $event->getModuleHook();
        self::assertNotNull($moduleHook);
        self::assertSame($moduleId, $moduleHook->getModuleId());
        self::assertSame($hook->getId(), $moduleHook->getHookId());
        self::assertFalse($moduleHook->getActive());
        self::assertGreaterThan(0, $moduleHook->getPosition());
    }

    public function testCreateModuleHookCleansIgnoredEntry(): void
    {
        $hook = $this->createTestHook('test.mh.ignored');
        $moduleId = $this->getActiveModuleId();

        // Pre-seed an IgnoredModuleHook row that should be cleaned up.
        $ignored = new \Thelia\Model\IgnoredModuleHook();
        $ignored
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setMethod('onMainFooter')
            ->setClassname('Thelia\\Module\\Cheque')
            ->save();

        self::assertGreaterThan(
            0,
            IgnoredModuleHookQuery::create()
                ->filterByHookId($hook->getId())
                ->filterByModuleId($moduleId)
                ->count(),
        );

        $event = new ModuleHookCreateEvent();
        $event
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onMainFooter')
            ->setTemplates('');

        $this->dispatch($event, TheliaEvents::MODULE_HOOK_CREATE);

        self::assertSame(
            0,
            IgnoredModuleHookQuery::create()
                ->filterByHookId($hook->getId())
                ->filterByModuleId($moduleId)
                ->count(),
        );
    }

    public function testUpdateModuleHookChangesClassnameAndMethod(): void
    {
        $hook = $this->createTestHook('test.mh.update');
        $moduleId = $this->getActiveModuleId();

        $createEvent = new ModuleHookCreateEvent();
        $createEvent
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onOriginal')
            ->setTemplates('');
        $this->dispatch($createEvent, TheliaEvents::MODULE_HOOK_CREATE);

        $moduleHook = $createEvent->getModuleHook();

        $updateEvent = new ModuleHookUpdateEvent();
        $updateEvent
            ->setModuleHookId($moduleHook->getId())
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onUpdated')
            ->setActive(true)
            ->setTemplates('front');

        $this->dispatch($updateEvent, TheliaEvents::MODULE_HOOK_UPDATE);

        $reloaded = ModuleHookQuery::create()->findPk($moduleHook->getId());
        self::assertNotNull($reloaded);
        self::assertSame('onUpdated', $reloaded->getMethod());
        self::assertTrue($reloaded->getActive());
        self::assertSame('front', $reloaded->getTemplates());
    }

    public function testToggleModuleHookActivationFlipsFlag(): void
    {
        $hook = $this->createTestHook('test.mh.toggle');
        $moduleId = $this->getActiveModuleId();

        $createEvent = new ModuleHookCreateEvent();
        $createEvent
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onRender')
            ->setTemplates('');
        $this->dispatch($createEvent, TheliaEvents::MODULE_HOOK_CREATE);

        $moduleHook = $createEvent->getModuleHook();
        self::assertFalse($moduleHook->getActive());

        // Set module_active = true so the toggle is allowed.
        $moduleHook->setModuleActive(true)->save();

        $toggleEvent = new ModuleHookToggleActivationEvent($moduleHook);
        $this->dispatch($toggleEvent, TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION);

        $reloaded = ModuleHookQuery::create()->findPk($moduleHook->getId());
        self::assertTrue($reloaded->getActive());
    }

    public function testToggleModuleHookActivationThrowsWhenModuleInactive(): void
    {
        $hook = $this->createTestHook('test.mh.toggle.fail');
        $moduleId = $this->getActiveModuleId();

        $createEvent = new ModuleHookCreateEvent();
        $createEvent
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onRender')
            ->setTemplates('');
        $this->dispatch($createEvent, TheliaEvents::MODULE_HOOK_CREATE);

        $moduleHook = $createEvent->getModuleHook();
        // Force module_active = false to trigger the exception.
        $moduleHook->setModuleActive(false)->save();

        $this->expectException(\LogicException::class);

        $toggleEvent = new ModuleHookToggleActivationEvent($moduleHook);
        $this->dispatch($toggleEvent, TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION);
    }

    public function testDeleteModuleHookRemovesRowAndCreatesIgnoredEntry(): void
    {
        $hook = $this->createTestHook('test.mh.delete');
        $moduleId = $this->getActiveModuleId();

        $createEvent = new ModuleHookCreateEvent();
        $createEvent
            ->setModuleId($moduleId)
            ->setHookId($hook->getId())
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod('onRender')
            ->setTemplates('');
        $this->dispatch($createEvent, TheliaEvents::MODULE_HOOK_CREATE);

        $moduleHookId = $createEvent->getModuleHook()->getId();

        $this->dispatch(
            new ModuleHookDeleteEvent($moduleHookId),
            TheliaEvents::MODULE_HOOK_DELETE,
        );

        self::assertNull(ModuleHookQuery::create()->findPk($moduleHookId));

        // An IgnoredModuleHook row must have been created.
        self::assertGreaterThan(
            0,
            IgnoredModuleHookQuery::create()
                ->filterByHookId($hook->getId())
                ->filterByModuleId($moduleId)
                ->count(),
        );
    }

    public function testUpdatePositionMovesModuleHookToAbsolutePosition(): void
    {
        $hook = $this->createTestHook('test.mh.pos');
        $moduleId = $this->getActiveModuleId();

        $mh1 = $this->createModuleHookBinding($moduleId, $hook->getId(), 'method1');
        $this->createModuleHookBinding($moduleId, $hook->getId(), 'method2');
        $this->createModuleHookBinding($moduleId, $hook->getId(), 'method3');

        $event = new UpdatePositionEvent(
            $mh1->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            3,
        );

        $this->dispatch($event, TheliaEvents::MODULE_HOOK_UPDATE_POSITION);

        self::assertSame(
            3,
            ModuleHookQuery::create()->findPk($mh1->getId())->getPosition(),
        );
    }

    private function createModuleHookBinding(int $moduleId, int $hookId, string $method): \Thelia\Model\ModuleHook
    {
        $event = new ModuleHookCreateEvent();
        $event
            ->setModuleId($moduleId)
            ->setHookId($hookId)
            ->setClassname('Thelia\\Module\\Cheque')
            ->setMethod($method)
            ->setTemplates('');

        return $this->dispatch($event, TheliaEvents::MODULE_HOOK_CREATE)->getModuleHook();
    }
}

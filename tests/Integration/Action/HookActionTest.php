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
use Thelia\Core\Event\Hook\HookDeleteEvent;
use Thelia\Core\Event\Hook\HookToggleActivationEvent;
use Thelia\Core\Event\Hook\HookToggleNativeEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\HookQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class HookActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsHookWithI18n(): void
    {
        $event = new HookCreateEvent();
        $event
            ->setLocale('en_US')
            ->setCode('test.hook.code')
            ->setType(TemplateDefinition::FRONT_OFFICE)
            ->setNative(false)
            ->setActive(true)
            ->setTitle('Test Hook');

        $this->dispatch($event, TheliaEvents::HOOK_CREATE);

        $hook = $event->getHook();
        self::assertNotNull($hook);
        self::assertSame('test.hook.code', $hook->getCode());
        self::assertSame('Test Hook', $hook->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesI18nAndFlags(): void
    {
        $hook = $this->dispatch(
            (new HookCreateEvent())
                ->setLocale('en_US')
                ->setCode('test.update.code')
                ->setType(TemplateDefinition::FRONT_OFFICE)
                ->setNative(false)
                ->setActive(true)
                ->setTitle('Old Title'),
            TheliaEvents::HOOK_CREATE,
        )->getHook();

        $event = new HookUpdateEvent($hook->getId());
        $event
            ->setLocale('en_US')
            ->setCode('test.update.code')
            ->setType(TemplateDefinition::FRONT_OFFICE)
            ->setNative(false)
            ->setActive(false)
            ->setBlock(true)
            ->setByModule(false)
            ->setTitle('New Title')
            ->setChapo('')
            ->setDescription('');

        $this->dispatch($event, TheliaEvents::HOOK_UPDATE);

        $reloaded = HookQuery::create()->findPk($hook->getId());
        self::assertSame('New Title', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame(0, (int) $reloaded->getActivate());
        self::assertSame(1, (int) $reloaded->getBlock());
    }

    public function testToggleActivationFlipsActiveFlag(): void
    {
        $hook = $this->dispatch(
            (new HookCreateEvent())
                ->setLocale('en_US')
                ->setCode('test.toggle.code')
                ->setType(TemplateDefinition::FRONT_OFFICE)
                ->setNative(false)
                ->setActive(true)
                ->setTitle('Togglable'),
            TheliaEvents::HOOK_CREATE,
        )->getHook();

        $this->dispatch(
            new HookToggleActivationEvent($hook->getId()),
            TheliaEvents::HOOK_TOGGLE_ACTIVATION,
        );

        self::assertSame(0, (int) HookQuery::create()->findPk($hook->getId())->getActivate());
    }

    public function testToggleNativeFlipsNativeFlag(): void
    {
        $hook = $this->dispatch(
            (new HookCreateEvent())
                ->setLocale('en_US')
                ->setCode('test.native.code')
                ->setType(TemplateDefinition::FRONT_OFFICE)
                ->setNative(false)
                ->setActive(true)
                ->setTitle('Togglable native'),
            TheliaEvents::HOOK_CREATE,
        )->getHook();

        $this->dispatch(
            new HookToggleNativeEvent($hook->getId()),
            TheliaEvents::HOOK_TOGGLE_NATIVE,
        );

        self::assertSame(1, (int) HookQuery::create()->findPk($hook->getId())->getNative());
    }

    public function testDeleteRemovesHook(): void
    {
        $hook = $this->dispatch(
            (new HookCreateEvent())
                ->setLocale('en_US')
                ->setCode('test.delete.code')
                ->setType(TemplateDefinition::FRONT_OFFICE)
                ->setNative(false)
                ->setActive(true)
                ->setTitle('Disposable'),
            TheliaEvents::HOOK_CREATE,
        )->getHook();
        $hookId = $hook->getId();

        $this->dispatch(new HookDeleteEvent($hookId), TheliaEvents::HOOK_DELETE);

        self::assertNull(HookQuery::create()->findPk($hookId));
    }
}

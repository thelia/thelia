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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Event\ModuleEvent;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * Propel announces a written row to the event dispatcher carried by the
 * connection. A model overriding one of those hooks has to hand the connection
 * over, or the announcement never leaves the model.
 */
final class ModuleWriteEventTest extends IntegrationTestCase
{
    #[Test]
    public function savingAModuleAnnouncesIt(): void
    {
        $module = null;

        $dispatched = $this->countEventsDispatchedWhile(
            ModuleEvent::POST_SAVE,
            function () use (&$module): void {
                $module = $this->createModule('ModuleWriteEventProbe');
            },
        );

        self::assertSame(1, $dispatched, 'Inserting a module must dispatch ModuleEvent::POST_SAVE.');

        $dispatched = $this->countEventsDispatchedWhile(
            ModuleEvent::POST_SAVE,
            static function () use ($module): void {
                $module->setVersion('2.0.0')->save();
            },
        );

        self::assertSame(1, $dispatched, 'Updating a module must dispatch it too.');
    }

    #[Test]
    public function deletingAModuleAnnouncesIt(): void
    {
        $module = $this->createModule('ModuleDeleteEventProbe');

        $dispatched = $this->countEventsDispatchedWhile(
            ModuleEvent::POST_DELETE,
            static function () use ($module): void {
                $module->delete();
            },
        );

        self::assertSame(1, $dispatched, 'Deleting a module must dispatch ModuleEvent::POST_DELETE.');
    }

    private function countEventsDispatchedWhile(string $eventName, callable $work): int
    {
        $count = 0;
        $listener = static function () use (&$count): void {
            ++$count;
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener($eventName, $listener);

        try {
            $work();
        } finally {
            $dispatcher->removeListener($eventName, $listener);
        }

        return $count;
    }

    private function createModule(string $code): Module
    {
        $module = (new Module())
            ->setCode($code)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(0)
            ->setFullNamespace($code.'\\'.$code);

        $module->save();

        return $module;
    }
}

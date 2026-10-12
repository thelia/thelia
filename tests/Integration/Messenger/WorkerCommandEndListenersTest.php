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

namespace Thelia\Tests\Integration\Messenger;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * A cache clear made by the shop asks the worker to stop and deletes the container
 * files; the worker then ends its command, which loads the listeners of the end of a
 * command. Loaded when the worker starts, they are there whatever the clear deleted.
 */
final class WorkerCommandEndListenersTest extends IntegrationTestCase
{
    public function testTheListenersOfTheEndOfTheCommandAreLoadedWhenTheWorkerStarts(): void
    {
        $container = self::$kernel->getContainer();
        self::assertFalse(self::isLoaded($container, 'console.error_listener'), 'A fresh kernel has not loaded the listener yet: the test would prove nothing.');

        $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerStartedEvent(new Worker([], $this->getService(MessageBusInterface::class))));

        self::assertTrue(self::isLoaded($container, 'console.error_listener'));
    }

    /**
     * Whether the service is built, public or private (initialized() only knows the
     * public ones).
     */
    private static function isLoaded(ContainerInterface $container, string $id): bool
    {
        $privates = (new \ReflectionProperty(Container::class, 'privates'))->getValue($container);

        return isset($privates[$id]) || ($container instanceof Container && $container->initialized($id));
    }
}

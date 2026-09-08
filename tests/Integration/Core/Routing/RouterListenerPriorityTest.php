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

namespace Thelia\Tests\Integration\Core\Routing;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Cmf\Component\Routing\ChainRouter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Test\IntegrationTestCase;

/**
 * Two RouterListener objects answer kernel.request: the one the core
 * registers, whose router chain holds the rewriting router, and the one
 * FrameworkBundle registers. Whichever runs second returns at once, because
 * _controller is already set, so the one that runs first is the one that
 * routes - and at equal priority that was decided by the order the two were
 * registered in.
 */
final class RouterListenerPriorityTest extends IntegrationTestCase
{
    #[Test]
    public function theListenerHoldingTheRewritingRouterRoutesTheRequest(): void
    {
        $routerListeners = $this->routerListenersOnRequest();

        self::assertCount(2, $routerListeners, 'This test has nothing to say if there is only one.');
        self::assertInstanceOf(
            ChainRouter::class,
            $this->routerOf($routerListeners[0]),
            'The first router listener to run must be the one whose chain holds the rewriting router.',
        );
    }

    #[Test]
    public function theDeclaredEventsAreTheOnesTheClassSubscribesTo(): void
    {
        $subscribed = $this->eventsRouterListenerSubscribesTo();
        $declared = $this->eventsTheCoreDeclares();

        self::assertSame(
            array_keys($subscribed),
            array_keys($declared),
            'The core declares the events of RouterListener one by one; the class now subscribes to another set.',
        );

        foreach ($subscribed as $event => $priority) {
            KernelEvents::REQUEST === $event
                ? self::assertGreaterThan(
                    $priority,
                    $declared[$event],
                    'The core listener must stay strictly in front of the one FrameworkBundle registers.',
                )
                : self::assertSame($priority, $declared[$event], \sprintf('The priority declared for %s must be the priority of the class.', $event));
        }
    }

    /**
     * @return list<RouterListener> in the order they run
     */
    private function routerListenersOnRequest(): array
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getService('event_dispatcher');

        $routerListeners = [];

        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            $object = \is_array($listener) ? ($listener[0] ?? null) : null;

            if ($object instanceof RouterListener) {
                $routerListeners[] = $object;
            }
        }

        return $routerListeners;
    }

    private function routerOf(RouterListener $listener): object
    {
        return (new \ReflectionProperty(RouterListener::class, 'matcher'))->getValue($listener);
    }

    /**
     * @return array<string, int> priority per event, as the class asks for it
     */
    private function eventsRouterListenerSubscribesTo(): array
    {
        $events = [];

        foreach (RouterListener::getSubscribedEvents() as $event => $parameters) {
            $priorities = \is_array($parameters[0]) ? $parameters : [$parameters];

            foreach ($priorities as $priority) {
                $events[$event] = $priority[1] ?? 0;
            }
        }

        return $events;
    }

    /**
     * @return array<string, int> priority per event, as the core declares it
     */
    private function eventsTheCoreDeclares(): array
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader(
            $container,
            new FileLocator(THELIA_LIB.'Config/Resources/services/core'),
            'prod',
        ))->load('routing.php');

        $definition = $container->getDefinition('listener.router');

        self::assertSame(
            [],
            $definition->getTag('kernel.event_subscriber'),
            'Declaring the events one by one and subscribing at the same time would register each of them twice.',
        );

        $events = [];

        foreach ($definition->getTag('kernel.event_listener') as $tag) {
            $events[$tag['event']] = $tag['priority'];
        }

        return $events;
    }
}

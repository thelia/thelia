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

namespace Thelia\Tests\Integration\Core\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\EventListener\ActiveLangsCacheListener;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A persistent worker runtime (FrankenPHP, RoadRunner) keeps the same process,
 * and with it the same memo, across every request it serves. Resetting it at
 * the start of each one bought a full read of the lang table for an answer a
 * write already invalidates.
 */
final class ActiveLangsCacheListenerTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        Lang::resetActiveLangsCache();

        parent::tearDown();
    }

    #[Test]
    public function theMemoSurvivesASecondRequestInTheSameProcess(): void
    {
        Lang::resetActiveLangsCache();
        Lang::getActiveLangs();

        $dispatcher = $this->listenerDispatcher();
        $request = Request::create('http://localhost');

        $statements = $this->recordSqlQueries(static function () use ($dispatcher, $request): void {
            $dispatcher->dispatch(
                new RequestEvent(
                    new class implements HttpKernelInterface {
                        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): never
                        {
                            throw new \LogicException('not called');
                        }
                    },
                    $request,
                    HttpKernelInterface::MAIN_REQUEST,
                ),
                KernelEvents::REQUEST,
            );

            Lang::getActiveLangs();
        });

        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'lang'),
            'A second request in the same process must not pay for the memo again: only a write drops it.',
        );
    }

    #[Test]
    public function aConsoleCommandStillDropsTheMemo(): void
    {
        Lang::getActiveLangs();

        $listener = new ActiveLangsCacheListener();
        $listener->onConsoleCommand(new ConsoleCommandEvent(
            new Command('demo:command'),
            new ArrayInput([]),
            new NullOutput(),
        ));

        $statements = $this->recordSqlQueries(static function (): void {
            Lang::getActiveLangs();
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'lang'),
            'A command boundary must still force a fresh read: nothing here can assume two commands share a runtime.',
        );
    }

    /**
     * Wires only the attributes the listener actually declares, the way the
     * container does: a method with no #[AsEventListener] for an event never
     * runs for it, whatever its name.
     */
    private function listenerDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $listener = new ActiveLangsCacheListener();

        foreach ((new \ReflectionClass(ActiveLangsCacheListener::class))->getMethods() as $method) {
            foreach ($method->getAttributes(AsEventListener::class) as $attribute) {
                $arguments = $attribute->getArguments();
                $dispatcher->addListener(
                    $arguments['event'],
                    [$listener, $method->getName()],
                    $arguments['priority'] ?? 0,
                );
            }
        }

        return $dispatcher;
    }
}

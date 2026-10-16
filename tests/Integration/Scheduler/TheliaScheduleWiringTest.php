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

namespace Thelia\Tests\Integration\Scheduler;

use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\DependencyInjection\AddScheduleMessengerPass;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Thelia\Scheduler\EventListener\RecurringTaskFailureListener;
use Thelia\Scheduler\TheliaSchedule;
use Thelia\Test\IntegrationTestCase;

/**
 * The thelia schedule as the container builds it: what `messenger:consume
 * scheduler_thelia` runs on a shop that sets nothing.
 */
final class TheliaScheduleWiringTest extends IntegrationTestCase
{
    public function testTheScheduleRunsTheTasksOfTheCoreOnTheirDefaultExpressions(): void
    {
        $schedule = $this->getService(TheliaSchedule::class)->getSchedule();

        self::assertSame(
            [
                'sale:check-activation' => '* * * * *',
                'maintenance:purge' => '30 3 * * *',
                'thelia:messenger:purge-failed' => '0 4 * * *',
            ],
            self::commands($schedule->getRecurringMessages()),
            'The currency rates are left out: the update overwrites every rate, those set by hand included.',
        );
    }

    /**
     * The schedule is a transport of its own, named as the listener of the failures
     * expects: a worker consumes it by that name, and a task that fails is kept for the
     * back office.
     */
    public function testTheScheduleIsConsumedAsTheTransportTheFailureListenerWatches(): void
    {
        $receivers = static::getContainer()->get('messenger.receiver_locator');
        \assert($receivers instanceof ContainerInterface);

        self::assertSame('scheduler_thelia', RecurringTaskFailureListener::RECEIVER);
        self::assertTrue($receivers->has(RecurringTaskFailureListener::RECEIVER), 'messenger:consume knows no receiver of that name.');
        self::assertInstanceOf(SchedulerTransport::class, $receivers->get(RecurringTaskFailureListener::RECEIVER));
    }

    public function testTheFailureAndTheSuccessOfATaskReachTheListenerThatKeepsThem(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $listener = $this->getService(RecurringTaskFailureListener::class);

        self::assertContains([$listener, 'onFailed'], $dispatcher->getListeners(WorkerMessageFailedEvent::class));
        self::assertContains([$listener, 'onHandled'], $dispatcher->getListeners(WorkerMessageHandledEvent::class));
    }

    /**
     * A module adds a task to the schedule of the shop with `#[AsCronTask(..., schedule:
     * 'thelia')]`: Symfony tags the service so, and the task joins those of the core
     * rather than making a schedule of its own, which no worker would consume.
     */
    public function testATaskAModuleDeclaresForTheScheduleOfTheShopJoinsTheTasksOfTheCore(): void
    {
        $attribute = (new \ReflectionClass(TheliaSchedule::class))->getAttributes(AsSchedule::class)[0] ?? null;
        self::assertNotNull($attribute, 'The core schedule is the provider of a named schedule.');
        self::assertSame(TheliaSchedule::NAME, $attribute->newInstance()->name);

        $container = new ContainerBuilder();
        $container->register('messenger.transport_factory')->setSynthetic(true);
        $container->register('messenger.default_serializer')->setSynthetic(true);
        $container->register(TheliaSchedule::class, TheliaSchedule::class)
            ->setArguments([new Definition(ArrayAdapter::class), new Definition(LockFactory::class, [new Definition(InMemoryStore::class)]), '* * * * *', '30 3 * * *', '0 4 * * *', ''])
            ->addTag('scheduler.schedule_provider', ['name' => TheliaSchedule::NAME])
            ->setPublic(true);
        $container->register('stock_sync.nightly_check', \stdClass::class)
            ->addTag('scheduler.task', ['schedule' => TheliaSchedule::NAME, 'trigger' => 'cron', 'expression' => '0 5 * * *', 'method' => 'check']);
        $container->addCompilerPass(new AddScheduleMessengerPass());
        $container->compile();

        $provider = $container->get(TheliaSchedule::class);
        \assert($provider instanceof ScheduleProviderInterface);
        $recurringMessages = $provider->getSchedule()->getRecurringMessages();

        self::assertArrayHasKey('maintenance:purge', self::commands(array_filter($recurringMessages, static fn (RecurringMessage $message): bool => '0 5 * * *' !== (string) $message->getTrigger())), 'The tasks of the core are still there.');
        $moduleTasks = array_values(array_filter($recurringMessages, static fn (RecurringMessage $message): bool => '0 5 * * *' === (string) $message->getTrigger()));
        self::assertCount(1, $moduleTasks, 'The task of the module joined them.');
        $call = iterator_to_array($moduleTasks[0]->getMessages(new MessageContext(TheliaSchedule::NAME, 'id', $moduleTasks[0]->getTrigger(), new \DateTimeImmutable())))[0] ?? null;
        self::assertInstanceOf(ServiceCallMessage::class, $call);
        self::assertSame('stock_sync.nightly_check', $call->getServiceId());
    }

    /**
     * @param array<RecurringMessage> $recurringMessages
     *
     * @return array<string, string> command => cron expression
     */
    private static function commands(array $recurringMessages): array
    {
        $commands = [];

        foreach ($recurringMessages as $recurringMessage) {
            foreach ($recurringMessage->getMessages(new MessageContext(TheliaSchedule::NAME, 'id', $recurringMessage->getTrigger(), new \DateTimeImmutable())) as $message) {
                \assert($message instanceof RunCommandMessage);
                $commands[$message->input] = (string) $recurringMessage->getTrigger();
            }
        }

        return $commands;
    }
}

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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Thelia\Command\UnpaidOrderReminderCommand;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

final class UnpaidOrderReminderCommandTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        ConfigQuery::resetCache();
    }

    public function testADryRunListsWhatWouldBeDoneAndChangesNothing(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '168:cancel');
        $order = $this->factory->order(null, ['postage' => 20, 'statusCode' => OrderStatus::CODE_NOT_PAID]);
        $order->setCreatedAt(new \DateTimeImmutable('-8 days'))->save();

        $tester = $this->tester();
        $tester->execute(['--dry-run' => true, '--limit' => '1000']);

        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/'.preg_quote((string) $order->getRef(), '/').'\s*\|\s*168 h\s*\|\s*cancel\s*\|\s*planned/', $tester->getDisplay());
        self::assertSame(OrderStatus::CODE_NOT_PAID, (string) OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testWithoutAScheduleItSaysSo(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '');

        $tester = $this->tester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('No reminder schedule is set', $tester->getDisplay());
    }

    public function testASecondRunWhileOneIsGoingLeavesItAlone(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '168:cancel');
        $running = $this->getService(LockFactory::class)->createLock(UnpaidOrderReminderCommand::LOCK_NAME);
        self::assertTrue($running->acquire());

        try {
            $tester = $this->tester();
            $tester->execute([]);
        } finally {
            $running->release();
        }

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Another run is in progress', $tester->getDisplay());
    }

    public function testALimitBelowOneIsRefused(): void
    {
        $tester = $this->tester();
        $tester->execute(['--limit' => '0']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('order:remind-unpaid'));
    }
}

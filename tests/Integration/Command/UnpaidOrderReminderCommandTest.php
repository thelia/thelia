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
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Command\UnpaidOrderReminderCommand;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderRunner;
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

        ConfigQuery::write('url_site', '');
        $tester = $this->tester();
        $tester->execute(['--dry-run' => true, '--limit' => '1000']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('url_site', $tester->getDisplay(), 'Without the address of the shop, the links of the mails would carry whatever host the router has.');
        self::assertMatchesRegularExpression('/'.preg_quote((string) $order->getRef(), '/').'\s*\|\s*168 h\s*\|\s*cancel\s*\|\s*planned/', $tester->getDisplay());
        self::assertSame(OrderStatus::CODE_NOT_PAID, (string) OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testTheLinkOfTheMailPointsToTheShopFromTheCommandLine(): void
    {
        // No request behind a scheduled task: the address the merchant gave the shop is
        // the only one the link can carry.
        $routes = $this->getService(RouterInterface::class)->getRouteCollection();

        if (null === $routes->get(UnpaidOrderReminderRunner::PAYMENT_ROUTE)) {
            self::markTestSkipped('The front theme does not carry the route the reminder links to yet.');
        }

        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::write('url_site', 'https://shop.example.com/boutique');
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '24:order_payment_reminder');
        $order = $this->factory->order(null, ['postage' => 20, 'statusCode' => OrderStatus::CODE_NOT_PAID]);
        $order->setCreatedAt(new \DateTimeImmutable('-25 hours'))->save();
        $texts = [];
        $listener = static function (MessageEvent $event) use (&$texts): void {
            $message = $event->getMessage();

            if ($message instanceof Email && !$event->isQueued()) {
                $texts[$message->getTo()[0]->getAddress()] = (string) $message->getTextBody();
            }
        };
        $this->dispatcher->addListener(MessageEvent::class, $listener);

        try {
            $this->tester()->execute(['--limit' => '1000']);
        } finally {
            $this->dispatcher->removeListener(MessageEvent::class, $listener);
        }

        self::assertStringContainsString('https://shop.example.com/boutique/order/pay/', $texts[(string) $order->getCustomer()->getEmail()] ?? '');
    }

    public function testAStepNamingAMessageTheShopDoesNotHaveIsReported(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '24:no_such_reminder_message');

        $tester = $this->tester();
        $tester->execute([]);

        self::assertStringContainsString('no_such_reminder_message', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->getStatusCode(), 'A schedule that cannot send is what the scheduler of the host has to report.');
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

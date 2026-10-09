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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Controller\Configuration\UnpaidOrderReminderController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderSettings;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The reminder schedule as the merchant sets it, and the two places of the back office
 * that follow its first step: the unpaid alert of the dashboard and the urgent marker of
 * the order list.
 */
final class UnpaidOrderReminderSettingsTest extends WebIntegrationTestCase
{
    private const PAGE = '/admin/configuration/unpaid-order-reminder';

    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office theme it
        // is given, and one that predates the reminders has no such screen.
        if (!class_exists(UnpaidOrderReminderController::class)) {
            self::markTestSkipped('The installed back-office theme predates the unpaid order reminders.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $admin = $this->fixtures()->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
        // Written by the requests, rolled back with the rest: the cache follows.
        ConfigQuery::resetCache();
    }

    public function testThePageOffersTheStepsAndThePaymentModules(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '24:order_payment_reminder,168:cancel');

        $crawler = $this->client->request('GET', self::PAGE);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('24', $crawler->filter('[data-testid="reminder-step-delay-0"]')->attr('value'));
        self::assertSame('cancel', $crawler->filter('[data-testid="reminder-step-action-1"] option[selected]')->attr('value'));
        self::assertCount(1, $crawler->filter('[data-testid="reminder-excluded-module"][value="Cheque"]'));
    }

    public function testSavingWritesTheScheduleAndTheExcludedModules(): void
    {
        $crawler = $this->client->request('GET', self::PAGE);
        $form = $crawler->filter('[data-testid="reminder-save"]')->form();
        $values = $form->getPhpValues();
        $values['steps'][0] = ['delay' => '72', 'action' => 'order_payment_reminder'];
        $values['steps'][1] = ['delay' => '24', 'action' => 'order_payment_reminder'];
        $values['steps'][2] = ['delay' => '168', 'action' => 'cancel'];
        $values['excluded_modules'] = ['Cheque'];

        $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('24:order_payment_reminder,72:order_payment_reminder,168:cancel', ConfigQuery::read(UnpaidOrderReminderSettings::SCHEDULE_KEY));
        self::assertSame('Cheque', ConfigQuery::read(UnpaidOrderReminderSettings::EXCLUDED_MODULES_KEY));
    }

    public function testAScheduleThatCannotBeKeptIsRefusedAndNothingIsWritten(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '24:order_payment_reminder');
        $crawler = $this->client->request('GET', self::PAGE);
        $form = $crawler->filter('[data-testid="reminder-save"]')->form();
        $values = $form->getPhpValues();
        $values['steps'][0] = ['delay' => '24', 'action' => 'cancel'];
        $values['steps'][1] = ['delay' => '72', 'action' => 'order_payment_reminder'];

        $crawler = $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('last step', $crawler->filter('[data-testid="reminder-error"]')->text(''));
        self::assertSame('24:order_payment_reminder', ConfigQuery::read(UnpaidOrderReminderSettings::SCHEDULE_KEY));
    }

    public function testTheDashboardAndTheOrderListFollowTheFirstStep(): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, '6:order_payment_reminder');
        $status = $this->fixtures()->orderStatus(['code' => 'awaiting_wire_'.uniqid(), 'title' => 'Awaiting wire']);
        $status->setEquivalentCode(OrderStatus::CODE_NOT_PAID)->save($this->getPropelConnection());
        $order = $this->orderAged(8, (string) $status->getCode());

        $this->client->request('GET', '/admin');
        self::assertStringContainsString('unpaid orders over 6h', (string) $this->client->getResponse()->getContent());

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertGreaterThan(0, $crawler->filter('.bo-order-urgent:contains("'.$order->getRef().'")')->count(), 'An order in a status equivalent to not paid is followed up like one.');
    }

    public function testTheOrderHistoryNamesTheRemindersSent(): void
    {
        $order = $this->orderAged(30, OrderStatus::CODE_NOT_PAID);
        $this->getService(OrderHistoryRecorder::class)->record((int) $order->getId(), OrderHistoryEventType::PAYMENT_REMINDER_SENT->value, ['step' => 24, 'message' => 'order_payment_reminder']);

        $this->client->request('GET', '/admin/order/update/'.$order->getId());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Payment reminder sent (24 h step)', (string) $this->client->getResponse()->getContent());
    }

    private function orderAged(int $hours, string $statusCode): Order
    {
        $order = $this->fixtures()->order(null, ['postage' => 20, 'statusCode' => $statusCode]);
        $order->setCreatedAt(new \DateTimeImmutable(\sprintf('-%d hours', $hours)))->save($this->getPropelConnection());

        return $order;
    }

    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}

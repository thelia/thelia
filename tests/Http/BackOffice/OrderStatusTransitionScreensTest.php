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

use BackOfficeDefaultTwigBundle\Service\OrderStatus\OrderStatusActionWriter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusActionQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderStatusTransitionQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office side of the order status transitions: the transitions and actions
 * tabs of a status, the constrained selector of the order sheet, the forced
 * change reserved to an entitled profile, and the bulk change that skips and
 * names the orders the graph refuses.
 */
final class OrderStatusTransitionScreensTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        if (!class_exists(OrderStatusActionWriter::class)) {
            self::markTestSkipped('The installed back-office theme predates the order status transitions.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        // Built directly, not through createFixtureFactory(): that helper pushes a
        // synthetic request the security context would then read the session from.
        $this->factory = new FixtureFactory($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    public function testTheTransitionsTabListsTheOtherStatusesAndSavesTheAllowedOnes(): void
    {
        $this->loginAs($this->factory->admin());
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);
        $refunded = $this->orderStatus(OrderStatus::CODE_REFUNDED);

        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId().'?tab=transitions');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="order-status-transitions-free"]'), 'A status with no transition is shown as free.');
        self::assertCount(0, $crawler->filter('[data-testid="order-status-transition-sent"]'), 'A status is never a target of itself.');

        // Same-name checkboxes are one field for the crawler: post the selection directly,
        // with the token the rendered form carries.
        $form = $crawler->filter('[data-testid="order-status-transitions-form"]');
        $this->client->request('POST', (string) $form->attr('action'), [
            '_token' => $form->filter('input[name="_token"]')->attr('value'),
            'to_status_ids' => [$refunded->getId()],
        ]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $targets = OrderStatusTransitionQuery::create()->filterByFromStatusId($sent->getId())->select('ToStatusId')->find()->getData();
        self::assertSame([$refunded->getId()], array_map('intval', $targets));

        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('[data-testid="order-status-transitions-restricted"]'));
        self::assertTrue($crawler->filter('[data-testid="order-status-transition-refunded"]')->getNode(0)->hasAttribute('checked'));
    }

    public function testTheActionsTabAddsAnActionFromTheDialogAndListsIt(): void
    {
        $this->loginAs($this->factory->admin());
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);

        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId().'?tab=actions');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="order-status-action-create-submit"]')->form();
        $form['action_type'] = 'send_customer_email';
        $form['payload[send_customer_email][message_code]'] = 'order_confirmation';
        $this->client->submit($form);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $action = OrderStatusActionQuery::create()->filterByToStatusId($sent->getId())->filterByActionType('send_customer_email')->findOne();
        self::assertNotNull($action, 'The action must be persisted.');
        self::assertSame(['message_code' => 'order_confirmation'], $action->getDecodedPayload());
        self::assertTrue((bool) $action->getActive());

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('order_confirmation', $crawler->filter('[data-testid="order-status-actions-pane"]')->text());
    }

    public function testAnActionWithAnUnknownMessageIsRefusedWithAMessage(): void
    {
        $this->loginAs($this->factory->admin());
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);

        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId().'?tab=actions');
        $form = $crawler->filter('[data-testid="order-status-action-create-submit"]')->form();
        $form['action_type'] = 'send_customer_email';
        $form['payload[send_customer_email][message_code]']->disableValidation()->setValue('no_such_message');
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        self::assertSame(0, OrderStatusActionQuery::create()->filterByToStatusId($sent->getId())->count());
        self::assertStringContainsString('no_such_message', $crawler->filter('[data-testid="bo-flash-danger"]')->text());
    }

    public function testTheOrderSheetOnlyOffersTheStatusesTheGraphAllows(): void
    {
        $this->loginAs($this->factory->admin());
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $offered = $crawler->filter('[data-testid="order-status-select"] option')->each(static fn ($option): string => $option->attr('value'));
        self::assertSame(
            [(string) $order->getStatusId(), (string) $this->orderStatus(OrderStatus::CODE_REFUNDED)->getId()],
            $offered,
            'The current status, then only what the graph allows.',
        );
        self::assertCount(1, $crawler->filter('[data-testid="order-status-restricted-hint"]'));
        self::assertCount(0, $crawler->filter('[data-testid="order-cancel-btn"]'), 'Canceling is not offered when the graph refuses it.');
        self::assertCount(1, $crawler->filter('[data-testid="order-status-force"]'), 'A superadministrator may force.');
    }

    public function testAForcedChangeNeedsItsOwnRight(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $notPaid = $this->orderStatus(OrderStatus::CODE_NOT_PAID);

        $this->loginAs($this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE]]));

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('[data-testid="order-status-force"]'), 'Without the right, the force control is not rendered.');

        $token = $crawler->filter('[data-testid="order-status-form"]')->attr('action');
        $token = substr((string) $token, strpos((string) $token, '_token=') + 7);
        $this->client->request('POST', '/admin/order/update/'.$order->getId().'/status?_token='.$token, ['status_id' => $notPaid->getId(), 'force' => '1']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(OrderStatus::CODE_SENT, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAnEntitledAdministratorForcesTheChangeAndItIsLogged(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $notPaid = $this->orderStatus(OrderStatus::CODE_NOT_PAID);

        $this->loginAs($this->factory->restrictedAdmin([
            AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE],
            AdminResources::ORDER_STATUS_FORCE => [AccessManager::UPDATE],
        ]));

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        $form = $crawler->filter('[data-testid="order-status-force-submit"]')->form();
        $form['status_id'] = (string) $notPaid->getId();
        $this->client->submit($form);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        self::assertSame(OrderStatus::CODE_NOT_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
        $log = AdminLogQuery::create()->filterByResourceId($order->getId())->orderById('DESC')->findOne();
        self::assertNotNull($log);
        self::assertStringContainsString('Forced order '.$order->getRef(), (string) $log->getMessage());
    }

    public function testTheOrderSheetShowsTheForcedChangesItHasReceived(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $notPaid = $this->orderStatus(OrderStatus::CODE_NOT_PAID);

        $this->loginAs($this->factory->restrictedAdmin([
            AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE],
            AdminResources::ORDER_STATUS_FORCE => [AccessManager::UPDATE],
        ], ['firstname' => 'Norma', 'lastname' => 'Jennings']));

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertCount(0, $crawler->filter('[data-testid="order-forced-status-changes"]'), 'An order nobody forced shows no override.');

        $form = $crawler->filter('[data-testid="order-status-force-submit"]')->form();
        $form['status_id'] = (string) $notPaid->getId();
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $overrides = $crawler->filter('[data-testid="order-forced-status-changes"]');
        self::assertCount(1, $overrides, 'The order sheet shows the override it just received.');

        $text = $overrides->text();
        self::assertStringContainsString($this->statusTitle(OrderStatus::CODE_SENT), $text, 'The status it was forced out of.');
        self::assertStringContainsString($this->statusTitle(OrderStatus::CODE_NOT_PAID), $text, 'The status it was forced into.');
        self::assertStringContainsString('Norma Jennings', $text, 'The administrator who forced it.');
    }

    public function testARefusedChangeFromTheOrderSheetIsExplainedAndLeavesTheOrderUntouched(): void
    {
        $this->loginAs($this->factory->admin());
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        $action = (string) $crawler->filter('[data-testid="order-status-form"]')->attr('action');
        $this->client->request('POST', $action, ['status_id' => $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()]);
        $crawler = $this->client->followRedirect();

        // The back office explains in its own words, before the core guard would have to.
        self::assertStringContainsString('Change the transitions of the status', $crawler->filter('[data-testid="bo-flash-danger"]')->text());
        self::assertSame(OrderStatus::CODE_SENT, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testTheBulkChangeSkipsAndNamesTheOrdersTheGraphRefuses(): void
    {
        $this->loginAs($this->factory->admin());
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $sentOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $paidOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $canceled = $this->orderStatus(OrderStatus::CODE_CANCELED);

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="order-bulk-status-form"]'));

        $form = $crawler->filter('[data-testid="order-bulk-status-submit"]')->form();
        $token = $form->get('_token')->getValue();
        $this->client->request('POST', '/admin/order/update/status', [
            '_token' => $token,
            'status_id' => $canceled->getId(),
            'order_ids' => [$sentOrder->getId(), $paidOrder->getId()],
        ]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();

        self::assertSame(OrderStatus::CODE_CANCELED, OrderQuery::create()->findPk($paidOrder->getId())->getOrderStatus()->getCode());
        self::assertSame(OrderStatus::CODE_SENT, OrderQuery::create()->findPk($sentOrder->getId())->getOrderStatus()->getCode());
        self::assertStringContainsString($sentOrder->getRef(), $crawler->filter('[data-testid="bo-flash-warning"]')->text(), 'The skipped order is named.');
        self::assertStringContainsString('1 order(s)', $crawler->filter('[data-testid="bo-flash-success"]')->text());
    }

    public function testTheBulkSelectorCarriesTheStatusesEachTargetIsWithinReachOf(): void
    {
        $this->loginAs($this->factory->admin());
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $sentOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertSame(
            (string) $sent->getId(),
            $crawler->filter('[data-testid="datatable-select-'.$sentOrder->getId().'"]')->attr('data-bulk-state'),
            'Each row states the status it is in.',
        );

        $reachableFrom = function (string $code) use ($crawler): array {
            $option = $crawler->filter('[data-testid="order-bulk-status-select"] option[value="'.$this->orderStatus($code)->getId().'"]');
            self::assertCount(1, $option);

            return array_filter(explode(',', (string) $option->attr('data-bulk-from')));
        };

        self::assertContains((string) $sent->getId(), $reachableFrom(OrderStatus::CODE_REFUNDED), 'The one transition declared from sent.');
        self::assertNotContains((string) $sent->getId(), $reachableFrom(OrderStatus::CODE_NOT_PAID), 'Sent no longer reaches anything else.');
        self::assertContains((string) $this->orderStatus(OrderStatus::CODE_PAID)->getId(), $reachableFrom(OrderStatus::CODE_NOT_PAID), 'A free status still reaches everything.');
    }

    public function testARefusedChangeIsNotEvenExplainedToAnAdministratorWithoutTheUpdateRight(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->loginAs($this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]));

        $this->client->request('GET', '/admin/order/update/'.$order->getId().'/status?status_id='.$this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId());
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'The right is checked before anything about the order is said.');

        $this->client->request('GET', '/admin/order/list/cancel/'.$order->getId());
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAWriteWithAnInvalidTokenIsRefusedWithAMessageNotAnError(): void
    {
        $this->loginAs($this->factory->admin());
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);
        $action = $this->getService(OrderStatusActionWriter::class)->create($sent->getId(), \Thelia\Domain\Order\Enum\OrderStatusActionTrigger::ENTER, null, 'allocate_invoice_ref', []);

        // Prime the session token, as any page does, then present another one.
        $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId());
        $this->client->request('GET', '/admin/configuration/order-status/actions/'.$action->getId().'/toggle?_token=not-the-token');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();

        self::assertCount(1, $crawler->filter('[data-testid="bo-flash-danger"]'));
        self::assertTrue((bool) OrderStatusActionQuery::create()->findPk($action->getId())->getActive(), 'Nothing was written.');

        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId().'?tab=transitions');
        $form = $crawler->filter('[data-testid="order-status-transitions-form"]');
        $this->client->request('POST', (string) $form->attr('action'), ['_token' => 'not-the-token', 'to_status_ids' => [$this->orderStatus(OrderStatus::CODE_REFUNDED)->getId()]]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, OrderStatusTransitionQuery::create()->filterByFromStatusId($sent->getId())->count());
    }

    public function testAnActionCanBeSwitchedOffReorderedAndRemoved(): void
    {
        $this->loginAs($this->factory->admin());
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);
        $writer = $this->getService(OrderStatusActionWriter::class);
        $trigger = \Thelia\Domain\Order\Enum\OrderStatusActionTrigger::ENTER;
        $first = $writer->create($sent->getId(), $trigger, null, 'allocate_invoice_ref', []);
        $second = $writer->create($sent->getId(), $trigger, null, 'release_coupons', []);

        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$sent->getId().'?tab=actions');
        $token = (string) $crawler->filter('[data-testid="order-status-action-create-form"] input[name="_token"]')->attr('value');

        $this->client->request('GET', '/admin/configuration/order-status/actions/'.$first->getId().'/toggle?_token='.$token);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertFalse((bool) OrderStatusActionQuery::create()->findPk($first->getId())->getActive());

        $this->client->request('GET', '/admin/configuration/order-status/actions/move?action_id='.$second->getId().'&position=1&_token='.$token);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, (int) OrderStatusActionQuery::create()->findPk($second->getId())->getPosition());
        self::assertSame(2, (int) OrderStatusActionQuery::create()->findPk($first->getId())->getPosition(), 'Positions stay dense after a move.');

        $this->client->request('POST', '/admin/configuration/order-status/actions/delete', ['_token' => $token, 'action_id' => $first->getId()]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull(OrderStatusActionQuery::create()->findPk($first->getId()));
    }

    public function testTheListHidesTheCancelActionWhenTheGraphRefusesIt(): void
    {
        $this->loginAs($this->factory->admin());
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $sentOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $paidOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $crawler = $this->client->request('GET', '/admin/orders');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertCount(0, $crawler->filter('[data-order-id="'.$sentOrder->getId().'"][data-testid^="datatable-action-cancel"]'), 'No cancel action on an order the graph cannot cancel.');
        self::assertGreaterThan(0, $crawler->filter('[data-order-id="'.$paidOrder->getId().'"][data-testid^="datatable-action-cancel"]')->count(), 'A free status still offers to cancel.');
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    /**
     * @param list<string> $toCodes
     */
    private function allowOnly(string $fromCode, array $toCodes): void
    {
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets(
            $this->orderStatus($fromCode)->getId(),
            array_map(fn (string $code): int => $this->orderStatus($code)->getId(), $toCodes),
        );
    }

    private function statusTitle(string $code): string
    {
        return (string) $this->orderStatus($code)->setLocale('en_US')->getTitle();
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Seeded order status '$code' is missing.");

        return $status;
    }
}

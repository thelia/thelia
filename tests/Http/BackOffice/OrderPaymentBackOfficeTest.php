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

use BackOfficeDefaultTwigBundle\Service\Order\OrderPaymentContextBuilder;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * The payment block of the order sheet: the journal, the amounts an authorization
 * holds, and the capture dialog.
 */
final class OrderPaymentBackOfficeTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private PaymentTransactionRecorder $recorder;

    protected function setUp(): void
    {
        if (!class_exists(OrderPaymentContextBuilder::class)) {
            self::markTestSkipped('The installed back-office theme predates the payment journal.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        // Built directly, not through createFixtureFactory(): that helper pushes a
        // synthetic request the security context would then read the session from.
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredCapturePaymentModule::reset();

        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testAChequeOrderMarkedPaidShowsItsCaptureLineAndNoCaptureButton(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);
        $this->markPaid($order);

        $crawler = $this->sheet($order);

        $lines = $crawler->filter('[data-testid="order-payment-line"]');
        self::assertCount(1, $lines);
        self::assertSame('capture', $lines->attr('data-payment-type'));
        self::assertSame('succeeded', $lines->attr('data-payment-state'));
        self::assertStringContainsString('120.00', $lines->text());
        self::assertStringContainsString('Cheque', $lines->text());
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-capture-btn"]'), 'A module that takes the price at once has nothing to capture.');
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-totals"]'), 'Without an authorization there is nothing to add up.');
    }

    public function testAnOrderWithoutAJournalShowsAnEmptyBlockWithoutError(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->factory->order(null, ['postage' => 120]);

        $crawler = $this->sheet($order);

        self::assertCount(1, $crawler->filter('[data-testid="order-payment-journal-empty"]'));
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-line"]'));
    }

    public function testAnAuthorizedOrderShowsWhatIsHeldAndOffersTheCapture(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        $this->recorder->recordCapture($order, 50, 'CAP-1', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $crawler = $this->sheet($order);

        self::assertStringContainsString('120.00', $crawler->filter('[data-testid="order-payment-authorized"]')->text());
        self::assertStringContainsString('50.00', $crawler->filter('[data-testid="order-payment-captured"]')->text());
        self::assertStringContainsString('70.00', $crawler->filter('[data-testid="order-payment-remaining"]')->text());
        self::assertCount(1, $crawler->filter('[data-testid="order-payment-capture-btn"]'));
        self::assertSame('70.00', $crawler->filter('[data-testid="order-payment-capture-amount"]')->attr('value'), 'The dialog is prefilled with the remainder.');
        self::assertCount(2, $crawler->filter('[data-testid="order-payment-line"]'));
    }

    public function testCapturingFromTheDialogWritesTheLinePaysTheOrderAndIsLogged(): void
    {
        $admin = $this->factory->admin();
        $this->loginAs($admin);
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);
        $this->client->request('POST', $this->captureUrl($order), [
            '_token' => $this->tokenOf($crawler),
            'amount' => '120.00',
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame([['order' => $order->getId(), 'amount' => 120.0, 'transaction' => $this->latestLineId($order)]], DeferredCapturePaymentModule::$captureCalls);
        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());

        $log = AdminLogQuery::create()
            ->filterByResource(AdminResources::ORDER_PAYMENT_CAPTURE)
            ->filterByAction(AccessManager::CREATE)
            ->orderById(Criteria::DESC)
            ->findOne();
        self::assertNotNull($log, 'The capture is written to the administration log.');
        self::assertStringContainsString((string) $order->getRef(), (string) $log->getMessage());
        self::assertStringContainsString('succeeded', (string) $log->getMessage());

        $crawler = $this->client->followRedirect();
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-capture-btn"]'), 'Nothing is left to capture.');
        self::assertCount(2, $crawler->filter('[data-testid="order-payment-line"]'));
    }

    public function testAPartialCaptureTypedWithACommaLeavesTheRest(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);
        $this->client->request('POST', $this->captureUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '50,00']);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('70.00', $crawler->filter('[data-testid="order-payment-remaining"]')->text());
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAnAmountAboveTheAuthorizationIsRefusedBeforeTheProviderIsCalled(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);
        $this->client->request('POST', $this->captureUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '500']);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame([], DeferredCapturePaymentModule::$captureCalls);
        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()), 'The authorization is the only line.');
    }

    public function testAProviderRefusalIsShownOnTheLineAndTheOrderStaysUnpaid(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::failed('05', 'Do not honor');

        $crawler = $this->sheet($order);
        $this->client->request('POST', $this->captureUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '120']);
        $crawler = $this->client->followRedirect();

        $failed = $crawler->filter('[data-testid="order-payment-line"][data-payment-state="failed"]');
        self::assertCount(1, $failed);
        self::assertStringContainsString('Do not honor', $crawler->filter('[data-testid="order-payment-line-error"]')->text());
        self::assertCount(1, $crawler->filter('[data-testid="order-payment-capture-btn"]'), 'The remainder is still there to capture.');
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAFailureOutsideThePaymentRulesIsNotShownToTheAdministrator(): void
    {
        // A listener of the capture — a module of the shop — fails with a message carrying
        // what it sent elsewhere. Only the payment rules word what the administrator reads.
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $failure = static function (): void {
            throw new \RuntimeException('Partner hook refused https://hook.example/notify?key=SECRET');
        };
        $dispatcher->addListener(TheliaEvents::ORDER_PAYMENT_CAPTURE, $failure, 1024);

        try {
            $crawler = $this->sheet($order);
            $this->client->request('POST', $this->captureUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '120']);
            $crawler = $this->client->followRedirect();
        } finally {
            $dispatcher->removeListener(TheliaEvents::ORDER_PAYMENT_CAPTURE, $failure);
        }

        self::assertStringNotContainsString('SECRET', $crawler->text());
        self::assertStringContainsString('The details are in the log', $crawler->text());
    }

    public function testAnAdministratorWithoutOrderAccessSeesNoSheetAndNoJournal(): void
    {
        $this->loginAs($this->factory->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));
        $order = $this->factory->order(null, ['postage' => 120]);
        $this->recorder->recordCapture($order, 120, 'SECRET-REF');

        $this->client->request('GET', '/admin/order/update/'.$order->getId());

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('SECRET-REF', (string) $this->client->getResponse()->getContent());
    }

    public function testReadingOrdersDoesNotOfferNorAllowTheCapture(): void
    {
        $this->loginAs($this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);
        self::assertCount(2, $crawler->filter('[data-testid="order-payment-line"], [data-testid="order-payment-totals"]'), 'The journal and the totals are read.');
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-capture-btn"]'), 'The button needs the capture right.');

        $this->client->request('POST', $this->captureUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '120']);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame([], DeferredCapturePaymentModule::$captureCalls);
    }

    public function testACaptureWithoutTheFormTokenTakesNothing(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        $this->sheet($order);

        $this->client->request('POST', $this->captureUrl($order), ['_token' => 'forged', 'amount' => '120']);

        self::assertSame([], DeferredCapturePaymentModule::$captureCalls);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testADoubleSubmitOfTheDialogCapturesOnce(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        $token = $this->tokenOf($this->sheet($order));

        $this->client->request('POST', $this->captureUrl($order), ['_token' => $token, 'amount' => '50']);
        $this->client->request('POST', $this->captureUrl($order), ['_token' => $token, 'amount' => '50']);

        self::assertCount(1, DeferredCapturePaymentModule::$captureCalls);
    }

    public function testTheSheetWarnsThatMarkingPaidByHandTakesNothingWhileAnAuthorizationHolds(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);

        self::assertCount(1, $crawler->filter('[data-testid="order-payment-hold-notice"]'));
    }

    public function testTheDialogNeverPrefillsMoreThanTheAuthorizationHolds(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->recorder->recordAuthorization($order, 100.005, 'AUTH-ODD', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $crawler = $this->sheet($order);

        self::assertSame('100.00', $crawler->filter('[data-testid="order-payment-capture-amount"]')->attr('value'));
    }

    public function testAJournalWithMovementsReplacesTheNoTransactionMention(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);

        $crawler = $this->sheet($order);

        self::assertStringNotContainsString('No transaction for this order', $crawler->filter('[data-testid="order-payment"]')->text());
    }

    public function testAProviderMessageIsReadOnItsOwnLineNotInAColumn(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::failed('05', 'Do not honor');
        $this->getService(\Thelia\Domain\Payment\Service\PaymentCaptureService::class)->capture($order);

        $crawler = $this->sheet($order);

        self::assertCount(0, $crawler->filter('[data-testid="order-payment"] table'), 'The narrow card holds no wide table.');
        self::assertStringContainsString('Do not honor', $crawler->filter('[data-testid="order-payment-line"][data-payment-state="failed"] [data-testid="order-payment-line-error"]')->text());
    }

    public function testWhatWasReleasedIsReadInTheTotals(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);
        $this->recorder->recordCapture($order, 20, 'CAP-20', moduleCode: DeferredCapturePaymentModule::getModuleCode());
        $this->recorder->recordVoid($order, 'VOID-100', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $crawler = $this->sheet($order);

        self::assertStringContainsString('100.00', $crawler->filter('[data-testid="order-payment-voided"]')->text(), 'Authorized 120, captured 20: the 100 released explains why nothing is left.');
    }

    public function testNothingReleasedShowsNoReleasedLine(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->authorizedOrder(120);

        self::assertCount(0, $this->sheet($order)->filter('[data-testid="order-payment-voided"]'));
    }

    private function authorizedOrder(float $total): Order
    {
        $order = $this->factory->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->recorder->recordAuthorization($order, $total, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());

        return $order;
    }

    private function markPaid(Order $order): void
    {
        $event = new OrderEvent($order);
        $event->setStatus((int) OrderStatusQuery::getPaidStatus()->getId());
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function sheet(Order $order): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    private function captureUrl(Order $order): string
    {
        return '/admin/order/update/'.$order->getId().'/payment-capture';
    }

    private function tokenOf(Crawler $crawler): string
    {
        return (string) $crawler->filter('[data-testid="order-status-form"] input[name="_token"]')->attr('value');
    }

    private function latestLineId(Order $order): int
    {
        return (int) OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0]->getId();
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    private function registerTheDeferredCaptureModule(): void
    {
        if (null !== ModuleQuery::create()->findOneByCode(DeferredCapturePaymentModule::getModuleCode())) {
            return;
        }

        (new Module())
            ->setCode(DeferredCapturePaymentModule::getModuleCode())
            ->setFullNamespace(DeferredCapturePaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->save($this->getPropelConnection());
    }
}

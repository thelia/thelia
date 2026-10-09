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

use BackOfficeDefaultTwigBundle\Controller\Order\OrderController;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Payment\Exception\PaymentRefusedException;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentRefundService;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * Giving money back from the payment card of the order sheet: through the provider when the
 * payment module can, recorded by hand otherwise, under a right of its own.
 */
final class OrderRefundBackOfficeTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private PaymentTransactionRecorder $recorder;

    protected function setUp(): void
    {
        if (!method_exists(OrderController::class, 'refundPayment')) {
            self::markTestSkipped('The installed back-office theme predates the refund.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
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

    public function testAPaidOrderOffersToRefundWhatWasCollected(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->paidOrder(100);

        $crawler = $this->sheet($order);

        self::assertCount(1, $crawler->filter('[data-testid="order-payment-refund-btn"][data-refund-mode="online"]'));
        self::assertSame('100.00', $crawler->filter('[data-testid="order-payment-refund-amount"]')->attr('value'));
        self::assertCount(5, $crawler->filter('[data-testid="order-payment-refund-reason"] option'));
    }

    public function testAFullRefundFromTheDialogRefundsTheOrderAndIsLogged(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->paidOrder(100);

        $this->postRefund($order, ['amount' => '', 'reason' => 'returned', 'comment' => 'Parcel came back']);

        $line = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId())[0];
        self::assertTrue($line->isSucceeded());
        self::assertSame('100.000000', $line->getAmount());
        self::assertSame(OrderStatus::CODE_REFUNDED, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
        self::assertSame('Parcel came back', DeferredCapturePaymentModule::$refundCalls[0]['comment']);
        self::assertSame(1, AdminLogQuery::create()->filterByMessage('%Payment refund of 100%', Criteria::LIKE)->count());
    }

    public function testAPartialRefundShowsWhatIsLeftAndASecondOneBeyondItIsRefused(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->paidOrder(100);

        $this->postRefund($order, ['amount' => '30,00', 'reason' => 'goodwill']);
        $crawler = $this->postRefund($order, ['amount' => '80', 'reason' => 'goodwill']);

        self::assertStringContainsString('exceeds', $crawler->filter('[data-testid="bo-flash-danger"]')->text(''));
        self::assertStringContainsString('30.00', $crawler->filter('[data-testid="order-payment-refunded"]')->text());
        self::assertStringContainsString('70.00', $crawler->filter('[data-testid="order-payment-refundable"]')->text());
        self::assertCount(1, DeferredCapturePaymentModule::$refundCalls);
        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAProviderRefusalIsShownAndNothingIsRefunded(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = new PaymentRefusedException('Refund window closed');

        $crawler = $this->postRefund($order, ['amount' => '40', 'reason' => 'other']);

        self::assertStringContainsString('Refund window closed', $crawler->filter('[data-testid="bo-flash-danger"]')->text(''));
        self::assertStringContainsString('100.00', $crawler->filter('[data-testid="order-payment-refundable"]')->text());
    }

    public function testAnOrderWhoseModuleCannotRefundOffersToRecordARefundMadeOutsideIt(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->factory->order(null, ['postage' => 100]);
        $this->recorder->recordCapture($order, 100, 'CHQ-'.$order->getId(), moduleCode: 'Cheque');

        $crawler = $this->sheet($order);
        self::assertCount(1, $crawler->filter('[data-testid="order-payment-refund-btn"][data-refund-mode="offline"]'));
        self::assertCount(1, $crawler->filter('[data-testid="order-payment-refund-offline-notice"]'));

        $crawler = $this->postRefund($order, ['amount' => '100', 'reason' => 'cancellation', 'comment' => 'Bank transfer']);

        $line = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId())[0];
        self::assertSame(PaymentRefundService::ERROR_CODE_OFFLINE, $line->getErrorCode());
        self::assertStringContainsString('Refund made outside the provider', $crawler->filter('[data-testid="order-payment-line"][data-payment-type="refund"]')->text());
        self::assertCount(0, $crawler->filter('[data-payment-type="refund"] [data-testid="order-payment-line-error"]'));
    }

    public function testARefundShownThroughTheProviderIsNotRecordedByHandWhenTheModuleStoppedRefunding(): void
    {
        $this->loginAs($this->factory->admin());
        $order = $this->paidOrder(100);

        // The page offered to refund through the provider; by the time the form is sent the
        // module no longer can. Recording it as made outside would claim money nobody sent.
        $crawler = $this->postRefund($order, ['amount' => '40', 'reason' => 'other'], static function (): void {
            DeferredCapturePaymentModule::$refunds = false;
        });

        self::assertStringContainsString('changed', $crawler->filter('[data-testid="bo-flash-danger"]')->text(''));
        self::assertCount(0, OrderPaymentTransactionQuery::create()->filterByOrderId((int) $order->getId())->filterByType('refund')->find());
    }

    public function testWithoutTheRefundRightThereIsNoButtonAndTheRequestIsRefused(): void
    {
        $this->loginAs($this->factory->restrictedAdmin([
            AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE],
            AdminResources::ORDER_PAYMENT_CAPTURE => [AccessManager::CREATE],
        ]));
        $order = $this->paidOrder(100);

        $crawler = $this->sheet($order);
        self::assertCount(0, $crawler->filter('[data-testid="order-payment-refund-btn"]'));

        $this->client->request('POST', $this->refundUrl($order), ['_token' => $this->tokenOf($crawler), 'amount' => '10', 'reason' => 'other']);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame([], DeferredCapturePaymentModule::$refundCalls);
    }

    /**
     * @param array<string, string> $fields
     */
    private function postRefund(Order $order, array $fields, ?\Closure $beforeSending = null): Crawler
    {
        $crawler = $this->sheet($order);
        $shownMode = (string) $crawler->filter('[data-testid="order-payment-refund-form"] input[name="mode"]')->attr('value');

        if (null !== $beforeSending) {
            $beforeSending();
        }

        $this->client->request('POST', $this->refundUrl($order), ['_token' => $this->tokenOf($crawler), 'mode' => $shownMode] + $fields);

        return $this->client->followRedirect();
    }

    private function paidOrder(float $total): Order
    {
        $order = $this->factory->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->recorder->recordAuthorization($order, $total, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());
        $this->getService(PaymentCaptureService::class)->capture($order);

        return $order;
    }

    private function sheet(Order $order): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    private function refundUrl(Order $order): string
    {
        return '/admin/order/update/'.$order->getId().'/payment-refund';
    }

    private function tokenOf(Crawler $crawler): string
    {
        return (string) $crawler->filter('[data-testid="order-status-form"] input[name="_token"]')->attr('value');
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

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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

final class OrderPaymentApiTest extends ApiTestCase
{
    private PaymentTransactionRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredCapturePaymentModule::reset();
        parent::tearDown();
    }

    public function testTheJournalOfAnOrderIsReadNewestFirstWithItsFields(): void
    {
        $order = $this->chequeOrder();
        $authorization = $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $this->recorder->recordCapture($order, 50, 'CAP-1', authorization: $authorization, moduleCode: 'Cheque');

        $members = $this->journalOf($order)['hydra:member'];

        self::assertCount(2, $members);
        self::assertSame(['capture', 'authorization'], array_column($members, 'type'));

        $capture = $members[0];
        self::assertSame(PaymentTransactionState::SUCCEEDED->value, $capture['state']);
        self::assertSame('50.000000', $capture['amount']);
        self::assertSame($order->getCurrencyId(), $capture['currencyId']);
        self::assertSame('CAP-1', $capture['pspReference']);
        self::assertSame($authorization->getId(), $capture['parentId']);
        self::assertSame($order->getPaymentModuleId(), $capture['paymentModuleId']);
        self::assertSame(OrderHistoryActorType::MODULE->value, $capture['actorType']);
        self::assertSame('Cheque', $capture['actorLabel']);
        self::assertNotEmpty($capture['createdAt']);
        self::assertArrayNotHasKey('errorMessage', $capture, 'A field that holds nothing is left out.');
    }

    public function testTheLinesOfAnotherOrderNeverAppear(): void
    {
        $order = $this->chequeOrder();
        $other = $this->chequeOrder();
        $this->recorder->recordCapture($other, 120, 'CAP-OTHER');

        self::assertSame(0, $this->journalOf($order)['hydra:totalItems']);
    }

    public function testAnOrderWithoutAJournalReadsEmptyWithoutError(): void
    {
        $order = $this->chequeOrder();

        $journal = $this->journalOf($order);
        $summary = $this->summaryOf($order);

        self::assertSame([], $journal['hydra:member']);
        self::assertSame('0.000000', $summary['authorized']);
        self::assertSame('0.000000', $summary['remainingToCapture']);
        self::assertFalse($summary['supportsCapture']);
        self::assertSame('Cheque', $summary['paymentModuleCode']);
    }

    public function testTheSummaryAddsTheJournalUp(): void
    {
        [$order] = $this->authorizedOrder(120);
        $this->recorder->recordCapture($order, 50, 'CAP-1', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $summary = $this->summaryOf($order);

        self::assertSame($order->getId(), $summary['orderId']);
        self::assertSame('120.000000', $summary['authorized']);
        self::assertSame('50.000000', $summary['captured']);
        self::assertSame('70.000000', $summary['remainingToCapture']);
        self::assertTrue($summary['supportsCapture']);
    }

    public function testACaptureTakesTheWholeRemainderAndAnswersTheLine(): void
    {
        [$order, $authorization] = $this->authorizedOrder(120);

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => null], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $line = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(PaymentTransactionType::CAPTURE->value, $line['type']);
        self::assertSame(PaymentTransactionState::SUCCEEDED->value, $line['state']);
        self::assertSame('120.000000', $line['amount']);
        self::assertSame('CAP-1', $line['pspReference']);
        self::assertSame($authorization->getId(), $line['parentId']);
        self::assertSame(OrderHistoryActorType::ADMIN->value, $line['actorType'], 'The administrator who asked is the author.');

        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testAPartialCaptureLeavesTheRest(): void
    {
        [$order] = $this->authorizedOrder(120);

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 50], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('70.000000', $this->summaryOf($order)['remainingToCapture']);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testACaptureAboveTheAuthorizationIsRefusedAsABusinessError(): void
    {
        [$order] = $this->authorizedOrder(120);

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 120.01], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame([], DeferredCapturePaymentModule::$captureCalls, 'The provider was not called.');
        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testANonPositiveAmountIsRefused(): void
    {
        [$order] = $this->authorizedOrder(120);

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => -5], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testAModuleThatTakesThePriceAtOnceCannotBeCapturedByHand(): void
    {
        $order = $this->chequeOrder();

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => null], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testAProviderRefusalIsAnsweredAsAFailedLine(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::failed('05', 'Do not honor');

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => null], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $line = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(PaymentTransactionState::FAILED->value, $line['state']);
        self::assertSame('05', $line['errorCode']);
        self::assertSame('Do not honor', $line['errorMessage']);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
    }

    public function testTheSameAmountAskedAgainRightAwayIsNotTakenTwice(): void
    {
        [$order] = $this->authorizedOrder(120);

        $first = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 30], token: $this->authenticateAsAdmin());
        $repeat = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 30], token: $this->authenticateAsAdmin());

        self::assertSame(Response::HTTP_CREATED, $first->getStatusCode());
        self::assertSame(Response::HTTP_CONFLICT, $repeat->getStatusCode());
        self::assertCount(1, DeferredCapturePaymentModule::$captureCalls);
        self::assertSame('90.000000', $this->summaryOf($order)['remainingToCapture']);
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', '/api/admin/orders/999999/payment_transactions', token: $token)->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', '/api/admin/orders/999999/payment', token: $token)->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('POST', '/api/admin/orders/999999/capture', ['amount' => null], token: $token)->getStatusCode());
    }

    public function testAnAnonymousCallIsRefused(): void
    {
        $order = $this->chequeOrder();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest('GET', $this->journalPath($order))->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest('POST', $this->capturePath($order), [])->getStatusCode());
    }

    public function testAnAdministratorWithoutOrderAccessReadsNothing(): void
    {
        $order = $this->chequeOrder();
        $restricted = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::CUSTOMER => [AccessManager::VIEW]],
            ['password' => 'password'],
        );
        $token = $this->authenticateAsAdmin($restricted);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->jsonRequest('GET', $this->journalPath($order), token: $token)->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId().'/payment', token: $token)->getStatusCode());
    }

    public function testReadingOrdersDoesNotGrantTheCapture(): void
    {
        [$order] = $this->authorizedOrder(120);
        $reader = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE]],
            ['password' => 'password'],
        );
        $token = $this->authenticateAsAdmin($reader);

        self::assertSame(Response::HTTP_OK, $this->jsonRequest('GET', $this->journalPath($order), token: $token)->getStatusCode());

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => null], token: $token);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame([], DeferredCapturePaymentModule::$captureCalls);
    }

    public function testTheCaptureRightAloneAllowsTheCapture(): void
    {
        [$order] = $this->authorizedOrder(120);
        $cashier = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ORDER_PAYMENT_CAPTURE => [AccessManager::CREATE]],
            ['password' => 'password'],
        );

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => null], token: $this->authenticateAsAdmin($cashier));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    /**
     * @return array{Order, \Thelia\Model\OrderPaymentTransaction}
     */
    private function authorizedOrder(float $total): array
    {
        $order = $this->createFixtureFactory()->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $authorization = $this->recorder->recordAuthorization($order, $total, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());

        return [$order, $authorization];
    }

    private function chequeOrder(): Order
    {
        return $this->createFixtureFactory()->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);
    }

    private function journalPath(Order $order): string
    {
        return '/api/admin/orders/'.$order->getId().'/payment_transactions';
    }

    private function capturePath(Order $order): string
    {
        return '/api/admin/orders/'.$order->getId().'/capture';
    }

    private function journalOf(Order $order): array
    {
        $response = $this->jsonRequest('GET', $this->journalPath($order), token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function summaryOf(Order $order): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId().'/payment', token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
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

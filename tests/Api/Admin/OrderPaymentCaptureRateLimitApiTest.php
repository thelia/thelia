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
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\RateLimit\RateLimitTestEnvironment;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * Captures asked through the admin API are counted per administrator account, apart from the
 * administration budget: past the limit, the capture is refused with a delay and the payment
 * module is not called.
 */
final class OrderPaymentCaptureRateLimitApiTest extends ApiTestCase
{
    use RateLimitTestEnvironment;

    private const int CAPTURE_LIMIT = 2;

    protected function setUp(): void
    {
        $this->setLimit('THELIA_API_RATE_LIMIT_ORDER_CAPTURE', (string) self::CAPTURE_LIMIT);

        parent::setUp();

        $this->clearRateLimiterCounters();
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredCapturePaymentModule::reset();
        $this->restoreLimits();

        parent::tearDown();
    }

    public function testTheCaptureAfterTheLastAllowedOneIsRefusedWithADelayAndNeverReachesTheModule(): void
    {
        $order = $this->authorizedOrder(120);
        $token = $this->authenticateAsAdmin();

        // Different amounts, so that none of them is the repetition the capture service refuses.
        foreach ([10, 20] as $amount) {
            self::assertSame(
                Response::HTTP_CREATED,
                $this->jsonRequest('POST', $this->capturePath($order), ['amount' => $amount], token: $token)->getStatusCode(),
                \sprintf('The capture of %d is still within the budget.', $amount),
            );
        }

        $refused = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 30], token: $token);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        self::assertCount(self::CAPTURE_LIMIT, DeferredCapturePaymentModule::$captureCalls, 'The refused capture never reached the payment module.');
    }

    public function testEachAdministratorHasItsOwnBudget(): void
    {
        $order = $this->authorizedOrder(120);
        $token = $this->authenticateAsAdmin();

        foreach ([10, 20] as $amount) {
            $this->jsonRequest('POST', $this->capturePath($order), ['amount' => $amount], token: $token);
        }

        $cashier = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ORDER_PAYMENT_CAPTURE => [AccessManager::CREATE]],
            ['password' => 'password'],
        );

        $response = $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 30], token: $this->authenticateAsAdmin($cashier));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testACaptureRefusedForLackOfTheRightSpendsNoBudget(): void
    {
        $order = $this->authorizedOrder(120);
        $reader = $this->createFixtureFactory()->restrictedAdmin(
            [AdminResources::ORDER => [AccessManager::VIEW]],
            ['password' => 'password'],
        );
        $token = $this->authenticateAsAdmin($reader);

        for ($call = 1; $call <= self::CAPTURE_LIMIT + 1; ++$call) {
            self::assertSame(
                Response::HTTP_FORBIDDEN,
                $this->jsonRequest('POST', $this->capturePath($order), ['amount' => 10 * $call], token: $token)->getStatusCode(),
                \sprintf('Call %d is refused for the right, not for the rate.', $call),
            );
        }
    }

    private function authorizedOrder(float $total): Order
    {
        $order = $this->createFixtureFactory()->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->getService(PaymentTransactionRecorder::class)->recordAuthorization($order, $total, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());

        return $order;
    }

    private function capturePath(Order $order): string
    {
        return '/api/admin/orders/'.$order->getId().'/capture';
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

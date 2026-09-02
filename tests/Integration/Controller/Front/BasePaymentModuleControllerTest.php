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

namespace Thelia\Tests\Integration\Controller\Front;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\OrderQuery;
use Thelia\Module\BasePaymentModuleController;
use Thelia\Test\IntegrationTestCase;

/**
 * A payment module reaches these two redirects when the customer comes back from the
 * gateway, which is both the least testable and the least forgiving moment of a shop.
 * The route identifiers are plain strings resolved at that very instant, so they are
 * pinned here against the running container.
 */
final class BasePaymentModuleControllerTest extends IntegrationTestCase
{
    public function testAConfirmedPaymentSendsTheCustomerToTheOrderConfirmationPage(): void
    {
        $url = $this->urlOfRedirect(static fn (PaymentControllerUnderTest $controller) => $controller->redirectToSuccessPage(42));

        self::assertStringEndsWith('/checkout/confirm?order_id=42', $url);
    }

    public function testAFailedPaymentSendsTheCustomerToTheFailurePageWithTheOrderAndTheReason(): void
    {
        $url = $this->urlOfRedirect(
            static fn (PaymentControllerUnderTest $controller) => $controller->redirectToFailurePage(42, 'Card declined'),
        );

        self::assertStringEndsWith('/checkout/failed?order_id=42&message=Card%20declined', $url);
    }

    public function testAFailedPaymentWithoutAReasonStillReachesTheFailurePage(): void
    {
        $url = $this->urlOfRedirect(static fn (PaymentControllerUnderTest $controller) => $controller->redirectToFailurePage(42, null));

        self::assertStringEndsWith('/checkout/failed?order_id=42', $url);
    }

    /**
     * Payment gateways name their transactions with references such as "PSP-4F2A-7C10":
     * the column they are stored in is a 100-character string, so the reference the
     * module hands over must reach it as it was issued.
     */
    public function testTheTransactionReferenceOfTheGatewayIsSavedOnTheOrder(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->controller()->saveTransactionRef(
            $this->getService(EventDispatcherInterface::class),
            (int) $order->getId(),
            'PSP-4F2A-7C10',
        );

        self::assertSame(
            'PSP-4F2A-7C10',
            OrderQuery::create()->findPk($order->getId())->getTransactionRef(),
        );
    }

    private function urlOfRedirect(callable $redirect): string
    {
        try {
            $redirect($this->controller());
        } catch (RedirectException $redirectException) {
            return $redirectException->getUrl();
        }

        self::fail('Expected a RedirectException to be thrown.');
    }

    private function controller(): PaymentControllerUnderTest
    {
        $container = static::getContainer();

        $controller = new PaymentControllerUnderTest();
        $controller->container = $container;
        $controller->securityContext = $container->get(SecurityContext::class);
        $controller->requestStack = $container->get('request_stack');
        $controller->translator = $container->get('thelia.translator');

        return $controller;
    }
}

/**
 * Stands in for the return-from-gateway controller of a payment module: it inherits the
 * wiring under test and only names the module the log file is written for.
 */
final class PaymentControllerUnderTest extends BasePaymentModuleController
{
    protected function getModuleCode(): string
    {
        return 'TestPayment';
    }
}

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

/*
 * Accepts a quote, or orders the cart of a quote, from a process of its own, the way a second
 * request does: its own kernel, its own connection, its own transactions. Run by
 * QuoteAcceptanceConcurrencyTest with the environment of the test process, so that it works on
 * the same database. Prints, as JSON, what happened.
 *
 * Usage:
 *   php quote-in-another-process.php accept <quoteId> <customerId> <revisionNumber>
 *   php quote-in-another-process.php place <cartId> <customerId>
 */

use App\Kernel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteException;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\CartQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\QuoteQuery;

require dirname(__DIR__, 5).'/tests/bootstrap.php';

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

$container->get('thelia.translator');
$container->get('thelia.url.manager');
$request = Request::create('http://localhost');
$request->setSession(new Session(new MockArraySessionStorage()));
$container->get('request_stack')->push($request);

$action = $argv[1] ?? '';
$customer = CustomerQuery::create()->findPk((int) ($argv[3] ?? 0));
$dispatcher = $container->get(EventDispatcherInterface::class);
$outcome = ['action' => $action];

try {
    if ('accept' === $action) {
        $accepted = 0;
        $dispatcher->addListener(QuoteEvents::ACCEPTED, static function () use (&$accepted): void {
            ++$accepted;
        });

        $quote = QuoteQuery::create()->findPk((int) $argv[2]);
        $cart = $container->get(QuoteFacade::class)->accept($quote, $customer, (int) $argv[4]);
        $outcome += ['cart' => $cart->getId(), 'accepted_events' => $accepted];
    } elseif ('place' === $action) {
        $dispatcher->addListener(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(99.0, 0.0, 'VAT'));
            $event->stopPropagation();
        }, 512);
        $dispatcher->addListener(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        }, 512);
        $dispatcher->addListener(TheliaEvents::MODULE_PAY, static fn (OrderPaymentEvent $event) => $event->stopPropagation(), 512);
        $container->get(SecurityContext::class)->setCustomerUser($customer);

        $cart = CartQuery::create()->findPk((int) $argv[2]);
        $order = $container->get(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) ModuleQuery::create()->findOneByCode('Cheque')?->getId(),
            CurrencyQuery::create()->findPk($cart->getCurrencyId()),
        ))->placedOrder;
        $outcome += ['order' => $order->getId()];
    }
} catch (QuoteException $refusal) {
    $outcome += ['refused' => $refusal->reason()];
} catch (Throwable $failure) {
    $outcome += ['failed' => $failure::class.': '.$failure->getMessage()];
}

echo json_encode($outcome, \JSON_THROW_ON_ERROR);

$kernel->shutdown();

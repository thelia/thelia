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
 * Holds the stock of a cart, or places its order, from a process of its own, the way a second
 * buyer does: its own kernel, its own connection, the facades of the core. Run by
 * StockReservationConcurrencyTest with the environment of the test process, so that it works on
 * the same database. The stock is taken when the order is placed. Prints, as JSON, the outcome,
 * the product a refusal names and its message.
 *
 * Usage: php reserve-or-place-in-another-process.php hold <cart id>
 *        php reserve-or-place-in-another-process.php place <cart id> <customer id> <delivery order address id> <invoice order address id>
 */

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationConflictException;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Order\Exception\OrderPlacementConflictException;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;

require dirname(__DIR__, 3).'/tests/bootstrap.php';

$mode = $argv[1] ?? '';
[$cartId, $customerId, $deliveryAddressId, $invoiceAddressId] = array_map('intval', array_pad(array_slice($argv, 2, 4), 4, '0'));

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

$container->get('thelia.translator');
$container->get('thelia.url.manager');
$request = Request::create('http://localhost');
$request->setSession(new Session(new MockArraySessionStorage()));
$container->get('request_stack')->push($request);

// The settings the test committed, read from the database rather than from a warmed cache.
ConfigQuery::resetCache();

$dispatcher = $container->get(EventDispatcherInterface::class);
$dispatcher->addListener(
    TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
    static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
    512,
);

$cart = CartQuery::create()->findPk($cartId) ?? throw new RuntimeException('No cart '.$cartId);

try {
    if ('hold' === $mode) {
        $container->get(CheckoutFacade::class)->holdStock($cart);
        $outcome = ['outcome' => 'held', 'reference' => null, 'message' => null];
    } elseif ('place' === $mode) {
        $customer = CustomerQuery::create()->findPk($customerId) ?? throw new RuntimeException('No customer '.$customerId);
        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($deliveryAddressId)
            ->setInvoiceOrderAddressId($invoiceAddressId)
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $container->get(OrderFacade::class)->createOrder(
            $dispatcher,
            $sessionOrder,
            $cart->getCurrency(),
            Lang::getDefaultLanguage(),
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            recordConsentAnswers: false,
        );
        $outcome = ['outcome' => 'placed', 'reference' => null, 'message' => null];
    } else {
        throw new InvalidArgumentException('Unknown mode '.$mode);
    }
} catch (StockReservationConflictException|OrderPlacementConflictException $conflict) {
    $outcome = ['outcome' => 'conflict', 'reference' => null, 'message' => $conflict->getMessage()];
} catch (StockShortageException $shortage) {
    $outcome = ['outcome' => 'refused', 'reference' => $shortage->productReference, 'message' => $shortage->getMessage()];
}

echo json_encode($outcome, \JSON_THROW_ON_ERROR);

$kernel->shutdown();

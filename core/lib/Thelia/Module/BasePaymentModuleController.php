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

namespace Thelia\Module;

use Symfony\Component\Routing\Router;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Log\Destination\TlogDestinationFile;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

/**
 * This class implement the minimum.
 *
 * @author Thelia <info@thelia.net>
 */
abstract class BasePaymentModuleController extends BaseFrontController
{
    protected $log;

    /**
     * Return a module identifier used to calculate the name of the log file,
     * and in the log messages.
     *
     * @return string the module code
     */
    abstract protected function getModuleCode(): string;

    /**
     * Returns the module-specific logger, initializing it if required.
     *
     * @return Tlog a Tlog instance
     */
    protected function getLog(): Tlog
    {
        if (null === $this->log) {
            $this->log = Tlog::getNewInstance();

            $logFilePath = $this->getLogFilePath();

            $this->log->setPrefix('#LEVEL: #DATE #HOUR: ');
            $this->log->setDestinations(TlogDestinationFile::class);
            $this->log->setConfig(TlogDestinationFile::class, 0, $logFilePath);
            $this->log->setLevel(Tlog::INFO);
        }

        return $this->log;
    }

    /**
     * @return string the path to the module's log file
     */
    protected function getLogFilePath(): string
    {
        return \sprintf(THELIA_LOG_DIR.'%s.log', strtolower($this->getModuleCode()));
    }

    /**
     * Process the confirmation of an order. This method should be  called
     * once the module has performed the required checks to confirm a valid payment.
     *
     * @param int $orderId the order ID
     *
     * @throws \Exception
     */
    public function confirmPayment(EventDispatcherInterface $eventDispatcher, int $orderId): void
    {
        if (!($order = $this->getOrder($orderId)) instanceof Order) {
            return;
        }

        try {
            $this->getLog()->addInfo(
                $this->getTranslator()->trans(
                    'Processing confirmation of order ref. %ref, ID %id',
                    ['%ref' => $order->getRef(), '%id' => $order->getId()],
                ),
            );

            $event = new OrderEvent($order);

            $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

            $this->getLog()->addInfo(
                $this->getTranslator()->trans(
                    'Order ref. %ref, ID %id has been successfully paid.',
                    ['%ref' => $order->getRef(), '%id' => $order->getId()],
                ),
            );
        } catch (\Exception $exception) {
            $this->getLog()->addError(
                $this->getTranslator()->trans(
                    'Error occured while processing order ref. %ref, ID %id: %err',
                    [
                        '%err' => $exception->getMessage(),
                        '%ref' => $order->getRef(),
                        '%id' => $order->getId(),
                    ],
                ),
            );

            throw $exception;
        }
    }

    /**
     * Save the transaction/payment ref in the order.
     *
     * @param int $orderId        the order ID
     * @param int $transactionRef the transaction reference
     *
     * @throws \Exception
     */
    public function saveTransactionRef(EventDispatcherInterface $eventDispatcher, int $orderId, int $transactionRef): void
    {
        if (!($order = $this->getOrder($orderId)) instanceof Order) {
            return;
        }

        try {
            $event = new OrderEvent($order);

            $event->setTransactionRef($transactionRef);

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_TRANSACTION_REF);

            $this->getLog()->addInfo(
                $this->getTranslator()->trans(
                    'Payment transaction %transaction_ref for order ref. %ref, ID %id has been successfully saved.',
                    [
                        '%transaction_ref' => $transactionRef,
                        '%ref' => $order->getRef(),
                        '%id' => $order->getId(),
                    ],
                ),
            );
        } catch (\Exception $exception) {
            $this->getLog()->addError(
                $this->getTranslator()->trans(
                    'Error occurred while saving payment transaction %transaction_ref for order ID %id.',
                    [
                        '%transaction_ref' => $transactionRef,
                        '%id' => $orderId,
                    ],
                ),
            );

            throw $exception;
        }
    }

    /**
     * Process the cancellation of a payment on the payment gateway. The order will go back to the
     * "not paid" status.
     *
     * @param int $orderId the order ID
     */
    public function cancelPayment(EventDispatcherInterface $eventDispatcher, int $orderId): void
    {
        if (!($order = $this->getOrder($orderId)) instanceof Order) {
            return;
        }

        try {
            $this->getLog()->addInfo(
                $this->getTranslator()->trans(
                    'Processing cancelation of payment for order ref. %ref',
                    ['%ref' => $order->getRef()],
                ),
            );

            $event = new OrderEvent($order);

            $event->setStatus(OrderStatusQuery::getNotPaidStatus()->getId());

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

            $this->getLog()->addInfo(
                $this->getTranslator()->trans(
                    'Order ref. %ref is now unpaid.',
                    ['%ref' => $order->getRef()],
                ),
            );
        } catch (\Exception $exception) {
            $this->getLog()->addError(
                $this->getTranslator()->trans(
                    'Error occurred while cancelling order ref. %ref, ID %id: %err',
                    [
                        '%err' => $exception->getMessage(),
                        '%ref' => $order->getRef(),
                        '%id' => $order->getId(),
                    ],
                ),
            );

            throw $exception;
        }
    }

    /**
     * Get an order and issue a log message if not found.
     */
    protected function getOrder($orderId): ?Order
    {
        if (null === $order = OrderQuery::create()->findPk($orderId)) {
            $this->getLog()->addError(
                $this->getTranslator()->trans('Unknown order ID:  %id', ['%id' => $orderId]),
            );
        }

        return $order;
    }

    /**
     * Redirect the customer to the successful payment page.
     *
     * @param int $orderId the order ID
     */
    public function redirectToSuccessPage(int $orderId): void
    {
        $this->getLog()->addInfo('Redirecting customer to payment success page');

        throw new RedirectException($this->retrieveUrlFromRouteId('order.placed', [], ['order_id' => $orderId], Router::ABSOLUTE_PATH));
    }

    /**
     * Redirect the customer to the failure payment page. if $message is null, a generic message is displayed.
     *
     * @param int         $orderId the order ID
     * @param string|null $message an error message
     */
    public function redirectToFailurePage(int $orderId, ?string $message): void
    {
        $this->getLog()->addInfo('Redirecting customer to payment failure page');

        throw new RedirectException($this->retrieveUrlFromRouteId('order.failed', [], ['order_id' => $orderId, 'message' => $message], Router::ABSOLUTE_PATH));
    }
}

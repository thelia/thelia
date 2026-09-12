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

namespace Thelia\Action;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\OrderReturnStateMachine;
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\OrderReturn\Service\StockIncrementer;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn as OrderReturnModel;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;

/**
 * Drives a return through its state machine: it validates each transition,
 * restocks the received goods according to the shop setting, recomputes the
 * refundable amount, and lets the status change notify the customer.
 */
class OrderReturn extends BaseAction implements EventSubscriberInterface
{
    /**
     * Restock every received line whatever its condition.
     */
    public const RESTOCK_MODE_AUTO = 'auto';
    /**
     * Never restock automatically.
     */
    public const RESTOCK_MODE_NEVER = 'never';
    /**
     * Restock only the lines the merchant marked as resellable.
     */
    public const RESTOCK_MODE_RESELLABLE = 'resellable';

    public const RESTOCK_MODE_CONFIG_KEY = 'order_return_restock_mode';

    public function __construct(
        protected MailerFactory $mailer,
        protected OrderReturnStateMachine $stateMachine,
        protected StockIncrementer $stockIncrementer,
        protected RefundAmountCalculator $refundCalculator,
        protected ReturnEligibilityChecker $eligibility,
        protected OrderReturnComposer $composer,
    ) {
    }

    /**
     * Open a return: the caller hands over an unsaved return carrying its order,
     * its customer and its lines, and gets back a written one.
     *
     * Everything else belongs to the domain and is stamped here - the eligibility
     * of every line under the row lock that protects it, the refundable amount,
     * the reason wording snapshot, the opening status and the reference - so the
     * merchant screens, the API and a module all open a return the same way
     * instead of each writing their own rows.
     *
     * @throws ReturnNotAllowedException when a line may not be returned
     */
    public function create(OrderReturnEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $return = $event->getOrderReturn();
        $order = $return->getOrder();
        $customer = $return->getCustomer();

        if (!$order instanceof Order || !$customer instanceof Customer) {
            throw new ReturnNotAllowedException('A return needs both an order and the customer it belongs to.');
        }

        $initialStatusId = $this->composer->initialStatusId();

        if (null === $initialStatusId) {
            throw new ReturnNotAllowedException('The opening return status does not exist.');
        }

        // Checking how much of a line is still returnable and writing the return
        // that consumes it belong to the same transaction, or two callers
        // arriving together are both allowed the same last unit.
        $connection = Propel::getConnection(OrderReturnTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            // A customer-opened return goes through the full opening gate
            // (feature on, ownership, paid, window); the merchant-initiated
            // path does not.
            if (!$return->getCreatedByAdmin()) {
                $this->eligibility->assertOrderReturnable($order, $customer);
            }

            $lines = [];
            $requested = [];

            foreach ($return->getOrderReturnLines() as $line) {
                $orderProduct = $line->getOrderProduct();

                if (!$orderProduct instanceof OrderProduct) {
                    throw new ReturnNotAllowedException('A return line must name the order product it is about.');
                }

                $lines[] = [$line, $orderProduct];
                $requested[] = ['order_product' => $orderProduct, 'quantity' => (float) $line->getQuantity()];
            }

            if ([] === $requested) {
                throw new ReturnNotAllowedException('A return must carry at least one line.');
            }

            $refunds = $this->composer->priceRequestedLines($order, $customer, $requested);
            $total = 0.0;

            foreach ($lines as $index => [$line, $orderProduct]) {
                $line->setProductSaleElementsId($orderProduct->getProductSaleElementsId());
                $line->setRefundAmount(number_format($refunds[$index], 6, '.', ''));
                $total += $refunds[$index];
            }

            $reason = $return->getOrderReturnReason();

            if ($reason instanceof OrderReturnReason) {
                $return->setReasonTitle($this->composer->reasonTitleFor($reason, $order));
            }

            if ($return->getIncludePostage()) {
                $total += $this->composer->postageRefund($order);
            }

            $return
                ->setStatusId($initialStatusId)
                ->setRefundAmount(number_format(round($total, 2), 2, '.', ''))
                ->save($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $event->setOrderReturn($return);

        $dispatcher->dispatch($event, TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
    }

    /**
     * Point the reception of a return: what the merchant says came back, line by
     * line, is written, put back in stock according to the shop setting, and the
     * refundable amount is recomputed on the received quantities rather than the
     * requested ones. The return then moves to the received status.
     *
     * Recording and moving are one gesture on purpose: they are two writes on
     * the same rows, and splitting them would let the same units be restocked
     * twice - once here and once on the transition.
     *
     * The received quantities are read from {@see OrderReturnEvent::getReceivedLines()},
     * keyed by return line id.
     *
     * @throws ReturnNotAllowedException when a quantity is impossible, or the return is not open for reception
     */
    public function receive(OrderReturnEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $return = $event->getOrderReturn();
        $declaredLines = $event->getReceivedLines();

        $receivedStatus = OrderReturnStatusQuery::create()->findOneByCodeCached(OrderReturnStatus::CODE_RECEIVED);

        if (null === $receivedStatus) {
            throw new ReturnNotAllowedException('The received return status does not exist.');
        }

        $connection = Propel::getConnection(OrderReturnTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->lockReturn($return, $connection);

            $fromCode = $return->getStatusCode();

            if (null === $fromCode || !$this->stateMachine->canTransition($fromCode, OrderReturnStatus::CODE_RECEIVED)) {
                throw new ReturnNotAllowedException(\sprintf('The return cannot move from "%s" to "%s".', $fromCode ?? 'none', OrderReturnStatus::CODE_RECEIVED));
            }

            foreach ($return->getOrderReturnLines() as $line) {
                $declared = $declaredLines[(int) $line->getId()] ?? null;

                if (null === $declared) {
                    continue;
                }

                $quantity = (float) $declared['quantity'];

                if ($quantity < 0) {
                    throw new ReturnNotAllowedException('The received quantity cannot be negative.');
                }

                if ($quantity > (float) $line->getQuantity() + ReturnEligibilityChecker::QUANTITY_TOLERANCE) {
                    throw new ReturnNotAllowedException(\sprintf('The received quantity (%s) cannot exceed the returned quantity (%s) of this line.', $quantity, $line->getQuantity()));
                }

                $line->setQuantityReceived($quantity);

                if (\array_key_exists('condition', $declared)) {
                    $line->setReceivedCondition($declared['condition']);
                }

                if (\array_key_exists('resellable', $declared)) {
                    $line->setResellable((bool) $declared['resellable']);
                }

                $line->save($connection);
            }

            $this->restockReceivedLines($return, $connection);

            $return
                ->setRefundAmount(number_format($this->refundCalculator->compute($return, true), 2, '.', ''))
                ->setStatusId((int) $receivedStatus->getId())
                ->save($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $event->setOrderReturn($return);

        $dispatcher->dispatch($event, TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
    }

    /**
     * Move a return to a new status, enforcing the authorized transitions.
     *
     * @throws ReturnNotAllowedException when the transition is not authorized
     */
    public function updateStatus(OrderReturnEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $return = $event->getOrderReturn();
        $targetStatusId = $event->getTargetStatusId();

        if (null === $targetStatusId) {
            throw new ReturnNotAllowedException('No target status given for the return status update.');
        }

        $targetStatus = OrderReturnStatusQuery::create()->findPk($targetStatusId);

        if (null === $targetStatus) {
            throw new ReturnNotAllowedException('The target return status does not exist.');
        }

        $toCode = $targetStatus->getEffectiveCode();

        $connection = Propel::getConnection(OrderReturnTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->lockReturn($return, $connection);

            $fromCode = $return->getStatusCode();

            if (null === $fromCode || !$this->stateMachine->canTransition($fromCode, $toCode)) {
                throw new ReturnNotAllowedException(\sprintf('The return cannot move from "%s" to "%s".', $fromCode ?? 'none', $toCode));
            }

            if (OrderReturnStatus::CODE_RECEIVED === $toCode) {
                $this->restockReceivedLines($return, $connection);
                $return->setRefundAmount(number_format($this->refundCalculator->compute($return, true), 2, '.', ''));
            }

            if (OrderReturnStatus::CODE_REFUSED === $toCode && null !== $event->getRefusalReason()) {
                $return->setRefusalReason($event->getRefusalReason());
            }

            $return->setStatusId($targetStatusId)->save($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $event->setOrderReturn($return);

        $dispatcher->dispatch($event, TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
    }

    /**
     * Notify the customer of a return status change, when a message is configured for it.
     */
    public function sendStatusEmail(OrderReturnEvent $event): void
    {
        $return = $event->getOrderReturn();
        $customer = $return->getCustomer();

        if (null === $customer) {
            return;
        }

        $messageCode = 'order_return_status_changed';

        // The message is provided by the install/skeleton layer; stay a no-op
        // until it is configured so the state machine never fails on a missing
        // template.
        if (null === MessageQuery::create()->findOneByName($messageCode)) {
            return;
        }

        $this->mailer->sendEmailToCustomer($messageCode, $customer, [
            'return_id' => $return->getId(),
            'return_ref' => $return->getRef(),
            'order_id' => $return->getOrderId(),
            'order_ref' => $return->getOrder()?->getRef(),
            'status_code' => $return->getStatusCode(),
            'refusal_reason' => $return->getRefusalReason(),
        ]);
    }

    /**
     * Holds the return row until the end of the transaction in progress and
     * rereads it, so two callers cannot decide on the same stale status.
     */
    private function lockReturn(OrderReturnModel $return, ConnectionInterface $connection): void
    {
        $lock = $connection->prepare('SELECT `id` FROM `order_return` WHERE `id` = :id FOR UPDATE');
        $lock->bindValue(':id', $return->getId(), \PDO::PARAM_INT);
        $lock->execute();
        $lock->closeCursor();

        $return->reload(true, $connection);
    }

    private function restockReceivedLines(OrderReturnModel $return, ConnectionInterface $connection): void
    {
        $mode = ConfigQuery::read(self::RESTOCK_MODE_CONFIG_KEY, self::RESTOCK_MODE_RESELLABLE);

        if (self::RESTOCK_MODE_NEVER === $mode) {
            return;
        }

        foreach ($return->getOrderReturnLines() as $line) {
            $productSaleElementsId = $line->getProductSaleElementsId();
            $quantity = (float) $line->getQuantityReceived();

            if (null === $productSaleElementsId || $quantity <= 0) {
                continue;
            }

            if (self::RESTOCK_MODE_RESELLABLE === $mode && !$line->getResellable()) {
                continue;
            }

            $this->stockIncrementer->increment((int) $productSaleElementsId, $quantity, $connection);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_RETURN_CREATE => ['create', 128],
            TheliaEvents::ORDER_RETURN_RECEIVE => ['receive', 128],
            TheliaEvents::ORDER_RETURN_UPDATE_STATUS => ['updateStatus', 128],
            TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL => ['sendStatusEmail', 128],
        ];
    }
}

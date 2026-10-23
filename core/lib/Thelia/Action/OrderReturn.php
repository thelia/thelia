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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\OrderReturnStateMachine;
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransaction;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransactionInterface;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Domain\OrderReturn\Service\StockIncrementer;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
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
        // Optional so that a subclass written against the previous constructor
        // keeps working; the container always injects the shared instance,
        // which the eligibility checker reads too.
        protected OrderReturnWriteTransactionInterface $transaction = new OrderReturnWriteTransaction(),
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

        // Before any lock: a return naming the order of another customer is
        // refused without holding that order, even for the length of a refusal.
        if ((int) $order->getCustomerId() !== (int) $customer->getId()) {
            throw new ReturnNotAllowedException(ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE);
        }

        $orderProductIds = [];
        foreach ($return->getOrderReturnLines() as $line) {
            $orderProductIds[] = (int) $line->getOrderProductId();
        }

        // Checking how much of a line is still returnable and writing the return
        // that consumes it belong to the same transaction, the rows locked before
        // anything is read, or two callers arriving together are both allowed
        // the same last unit.
        $this->transaction->run((int) $order->getId(), $orderProductIds, function (ConnectionInterface $connection) use ($return, $order, $customer, $initialStatusId): void {
            // Read again once locked: the order the caller handed over was
            // loaded before, maybe before another request changed its status.
            // Nested in a transaction of the caller, this plain read returns
            // the picture of that transaction, as every other read of it does.
            $order = OrderQuery::create()->findPk($order->getId(), $connection)
                ?? throw new ReturnNotAllowedException('The order does not exist.');

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
        });

        $event->setOrderReturn($return);
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

        $this->inReturnTransaction($return, function (ConnectionInterface $connection) use ($return, $declaredLines, $receivedStatus): void {
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
        });

        $event->setOrderReturn($return);
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

        $this->inReturnTransaction($return, function (ConnectionInterface $connection) use ($return, $event, $targetStatusId, $toCode): void {
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
        });

        $event->setOrderReturn($return);
    }

    /**
     * Announces the gesture to the customer, once everything that records it has run.
     *
     * The mail used to be sent from the end of the three actions above, at priority 128,
     * which is before the listener that writes the line of the gesture in the history of
     * the order (64): the timeline showed the mail before what it announces. It is now
     * sent by its own listener, below that one, on the same event. It is still sent once
     * the action has committed, and not at all when the action throws.
     *
     * Two things follow from it. A direct call to create(), receive() or updateStatus()
     * no longer sends the mail: only the dispatch of the event does. And a listener
     * between 32 and 128 that throws or stops the propagation keeps the mail from going.
     */
    public function dispatchStatusEmail(OrderReturnEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
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
     * Runs $work in the transaction a return is written in, with the locks
     * every return write takes in the same order - the order, then the order
     * product lines of the return by increasing id - and then the return row:
     * a reception and a patch of one of its lines wait on each other, so the
     * reception never restocks a quantity a patch is changing, nor checks its
     * own against a quantity a patch is changing.
     *
     * The order product lines are read before the transaction, for the ids of
     * the rows to lock only.
     *
     * @param callable(ConnectionInterface): void $work
     */
    private function inReturnTransaction(OrderReturnModel $return, callable $work): void
    {
        /** @var list<int> $orderProductIds */
        $orderProductIds = array_map('intval', OrderReturnLineQuery::create()
            ->filterByOrderReturnId((int) $return->getId())
            ->select([OrderReturnLineTableMap::COL_ORDER_PRODUCT_ID])
            ->find()
            ->getData());

        $this->transaction->run((int) $return->getOrderId(), $orderProductIds, function (ConnectionInterface $connection) use ($return, $work): void {
            $this->lockReturn($return, $connection);
            $work($connection);
        });
    }

    /**
     * Holds the return row until the end of the transaction in progress and
     * rereads it with its lines, so two callers cannot decide on the same
     * stale status or quantities. The lines leave the instance pool first: a
     * deep reload reads them again, but a pooled line would come back as it
     * was loaded before the lock.
     *
     * In a transaction this reception opened, its plain reads see every commit
     * up to the locks, and a deep reload is enough. Nested in a transaction the
     * caller opened, a plain read returns the picture the caller took at its
     * first read, whatever was committed since: the return and its lines are
     * then read with locking reads, which see the last commit.
     */
    private function lockReturn(OrderReturnModel $return, ConnectionInterface $connection): void
    {
        OrderReturnLineTableMap::clearInstancePool();

        if (!$this->transaction->readsSeeCommits()) {
            $this->rereadLocked($return, $connection);

            return;
        }

        $lock = $connection->prepare('SELECT `id` FROM `order_return` WHERE `id` = :id FOR UPDATE');
        $lock->bindValue(':id', $return->getId(), \PDO::PARAM_INT);
        $lock->execute();
        $lock->closeCursor();

        $return->reload(true, $connection);
    }

    /**
     * Loads the return and its lines as they are now, each held by a locking
     * read on its primary key: the return first, then its lines by increasing
     * id, as a patch of a line takes them. The ids of the lines come from a
     * plain read, which is enough: a return never gains nor loses a line once
     * written, and a line that is gone is left out.
     */
    private function rereadLocked(OrderReturnModel $return, ConnectionInterface $connection): void
    {
        OrderReturnTableMap::removeInstanceFromPool((string) $return->getId());

        $dataFetcher = OrderReturnQuery::create()
            ->filterById($return->getId())
            ->lockForUpdate()
            ->setFormatter(ModelCriteria::FORMAT_STATEMENT)
            ->find($connection);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();

        if (!$row) {
            throw new ReturnNotAllowedException('The return does not exist.');
        }

        $return->hydrate($row, 0, true, $dataFetcher->getIndexType());

        /** @var list<int> $lineIds */
        $lineIds = array_map('intval', OrderReturnLineQuery::create()
            ->filterByOrderReturnId((int) $return->getId())
            ->orderById()
            ->select([OrderReturnLineTableMap::COL_ID])
            ->find($connection)
            ->getData());

        $return->clearOrderReturnLines();

        foreach ($lineIds as $lineId) {
            $line = OrderReturnLineQuery::create()->filterById($lineId)->lockForUpdate()->findOne($connection);

            if (null !== $line) {
                $return->addOrderReturnLine($line);
            }
        }

        $return->resetPartialOrderReturnLines(false);
    }

    private function restockReceivedLines(OrderReturnModel $return, ConnectionInterface $connection): void
    {
        $mode = ConfigQuery::read(self::RESTOCK_MODE_CONFIG_KEY, self::RESTOCK_MODE_RESELLABLE);

        if (self::RESTOCK_MODE_NEVER === $mode) {
            return;
        }

        $saleUnitId = $return->getOrder()?->getSaleUnitId();

        foreach ($return->getOrderReturnLines() as $line) {
            $productSaleElementsId = $line->getProductSaleElementsId();
            $quantity = (float) $line->getQuantityReceived();

            if (null === $productSaleElementsId || $quantity <= 0) {
                continue;
            }

            if (self::RESTOCK_MODE_RESELLABLE === $mode && !$line->getResellable()) {
                continue;
            }

            $this->stockIncrementer->increment((int) $productSaleElementsId, $quantity, $connection, $saleUnitId);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_RETURN_CREATE => [['create', 128], ['dispatchStatusEmail', 32]],
            TheliaEvents::ORDER_RETURN_RECEIVE => [['receive', 128], ['dispatchStatusEmail', 32]],
            TheliaEvents::ORDER_RETURN_UPDATE_STATUS => [['updateStatus', 128], ['dispatchStatusEmail', 32]],
            TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL => ['sendStatusEmail', 128],
        ];
    }
}

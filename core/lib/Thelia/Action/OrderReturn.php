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
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\OrderReturn\Service\StockIncrementer;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderReturn as OrderReturnModel;
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
    ) {
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
            $lock = $connection->prepare('SELECT `id` FROM `order_return` WHERE `id` = :id FOR UPDATE');
            $lock->bindValue(':id', $return->getId(), \PDO::PARAM_INT);
            $lock->execute();
            $return->reload(true, $connection);

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
            TheliaEvents::ORDER_RETURN_UPDATE_STATUS => ['updateStatus', 128],
            TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL => ['sendStatusEmail', 128],
        ];
    }
}

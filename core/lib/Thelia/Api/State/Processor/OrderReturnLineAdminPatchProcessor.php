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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturnLine as OrderReturnLineResource;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;
use Thelia\Domain\OrderReturn\OrderReturnStateMachine;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransactionInterface;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;

/**
 * The two quantities of a return line are what the rest of the feature counts
 * on, and the admin patch is the only way to change them.
 *
 * `quantityReceived` is what the reception restocks and what the refundable
 * amount is recomputed from, so it may never exceed what the customer asked
 * for: a merchant typing 20 instead of 2 would put eighteen units the shop
 * never got back into the stock, and refund them.
 *
 * `quantity` is the size of the return itself. Raising it is opening a bigger
 * return, and it goes through the same gate the creation does - under the same
 * row locks, in the same transaction - with the line being edited left out of
 * the cumulative count. Only that line: the other lines of its return may
 * hold units of the same order product, and still do.
 *
 * Every patch is decided on the line as it is once locked, never on the line
 * API Platform read before: the order and the order product are locked first,
 * as every return write does, then the return and the line. A line found
 * otherwise than API Platform read it for this request - another patch, a
 * reception committed between that read and the locks - answers 409: the
 * request is sent again on the line as it now is, and the fields it does not
 * send are never written back from a read the locks outdated. The 409 covers
 * that window only. It is not a check between two administrators who read the
 * line at different times: that would take an optimistic lock (an ETag the
 * client sends back in `If-Match`), and a PATCH applies to the line as the
 * server reads it, on the fields it sends.
 *
 * The patch changes what was received of a line, never what the line is
 * about: its order product and its return are not writable, and are checked
 * under the locks to be those the locks were taken on. Once the return can no
 * longer be received - received, settled, refused, expired - the stock and
 * the refund are settled on its lines, and they no longer change.
 */
final readonly class OrderReturnLineAdminPatchProcessor implements ProcessorInterface
{
    private const string CHANGED_SINCE_READ = 'The return line changed since it was read: read it again before changing it.';

    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private ReturnEligibilityChecker $eligibility,
        private OrderReturnWriteTransactionInterface $transaction,
        private OrderReturnStateMachine $stateMachine,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderReturnLineResource) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $lineId = (int) ($uriVariables['id'] ?? 0);

        // Read before any lock, for the ids of the rows to lock only, and read
        // again under the locks: a line moved in between is refused there.
        // Selected columns, so that nothing enters the instance pool.
        /** @var array{OrderProductId: int|string|null, OrderReturnId: int|string|null}|null $parents */
        $parents = OrderReturnLineQuery::create()
            ->filterById($lineId)
            ->select(['OrderProductId', 'OrderReturnId'])
            ->findOne();

        if (null === $parents) {
            throw new NotFoundHttpException('Return line not found.');
        }

        $orderProductId = (int) $parents['OrderProductId'];
        $returnId = (int) $parents['OrderReturnId'];
        $orderId = OrderProductQuery::create()->filterById($orderProductId)->select(['OrderId'])->findOne();

        if (null === $orderId) {
            throw new UnprocessableEntityHttpException('The return line is not attached to an order of a customer.');
        }

        $orderId = (int) $orderId;
        $previous = $context['previous_data'] ?? null;
        $previous = $previous instanceof OrderReturnLineResource ? $previous : null;

        try {
            return $this->transaction->run($orderId, [$orderProductId], function (ConnectionInterface $connection) use ($data, $previous, $operation, $uriVariables, $context, $lineId, $orderId, $orderProductId, $returnId): mixed {
                $return = $this->lockedReturn($returnId, $connection);
                $locked = $this->lockedLine($lineId, $connection);

                $this->assertTheLineIsAsItWasRead($locked, $data, $previous, $orderProductId, $returnId);
                $this->takeWhatNoClientWritesFrom($locked, $data);
                $this->assertTheLineMayStillChange($return, $locked, $data);

                $quantity = $data->getQuantity();
                $received = $data->getQuantityReceived() ?? 0.0;

                if ($received < 0) {
                    throw new UnprocessableEntityHttpException('The received quantity cannot be negative.');
                }

                if ($received > $quantity + ReturnEligibilityChecker::QUANTITY_TOLERANCE) {
                    throw new UnprocessableEntityHttpException(\sprintf('The received quantity (%s) cannot exceed the returned quantity (%s) of this line.', $received, $quantity));
                }

                if (!self::sameQuantity($quantity, (float) $locked->getQuantity())) {
                    $this->assertTheNewQuantityIsReturnable($orderId, $locked, $quantity, $connection);
                }

                return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            });
        } catch (ReturnRequestConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (ReturnNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * The return of the line, held until the end of the transaction - after
     * the order and its lines, as a reception takes it - and read as it is
     * now: out of the instance pool first, which would hand it back as loaded
     * before the lock.
     */
    private function lockedReturn(int $returnId, ConnectionInterface $connection): OrderReturn
    {
        OrderReturnTableMap::removeInstanceFromPool((string) $returnId);

        return OrderReturnQuery::create()
            ->filterById($returnId)
            ->lockForUpdate()
            ->findOne($connection)
            ?? throw new ConflictHttpException(self::CHANGED_SINCE_READ);
    }

    /**
     * The line as it is now, its parent rows locked: out of the instance pool
     * first, which would hand back the line as loaded before the lock, and
     * read with a lock of its own, which sees the last commit even in a
     * transaction a caller opened and already read in.
     */
    private function lockedLine(int $lineId, ConnectionInterface $connection): OrderReturnLine
    {
        OrderReturnLineTableMap::removeInstanceFromPool((string) $lineId);

        return OrderReturnLineQuery::create()
            ->filterById($lineId)
            ->lockForUpdate()
            ->findOne($connection)
            ?? throw new NotFoundHttpException('Return line not found.');
    }

    /**
     * A merge patch carries only the fields it changes, and the resource holds
     * the others as API Platform read them, before any lock: written back, they
     * would undo what was committed since. The line must therefore be, on
     * every field a patch writes, what that read found, and on its order
     * product and its return, what the locks were taken on.
     *
     * Without that read to compare to - the processor called outside of an
     * HTTP request - only an uninitialized quantity is taken from the line.
     */
    private function assertTheLineIsAsItWasRead(OrderReturnLine $locked, OrderReturnLineResource $data, ?OrderReturnLineResource $previous, int $orderProductId, int $returnId): void
    {
        if ((int) $locked->getOrderProductId() !== $orderProductId
            || (int) $locked->getOrderReturnId() !== $returnId
            || (isset($data->orderProduct) && $data->getOrderProduct()->getId() !== $orderProductId)
            || (null !== $data->getOrderReturn() && $data->getOrderReturn()->getId() !== $returnId)) {
            throw new ConflictHttpException(self::CHANGED_SINCE_READ);
        }

        if (null === $previous) {
            if (!isset($data->quantity)) {
                $data->setQuantity((float) $locked->getQuantity());
            }

            return;
        }

        if ((isset($previous->quantity) && !self::sameQuantity($previous->getQuantity(), (float) $locked->getQuantity()))
            || !self::sameQuantity($previous->getQuantityReceived() ?? 0.0, (float) $locked->getQuantityReceived())
            || $previous->getReceivedCondition() !== $locked->getReceivedCondition()
            || (bool) $previous->getResellable() !== (bool) $locked->getResellable()) {
            throw new ConflictHttpException(self::CHANGED_SINCE_READ);
        }

        if (!isset($data->quantity)) {
            $data->setQuantity((float) $locked->getQuantity());
        }
    }

    /**
     * The fields no client may write come from the locked line: the persist
     * processor writes every field of the resource back.
     */
    private function takeWhatNoClientWritesFrom(OrderReturnLine $locked, OrderReturnLineResource $data): void
    {
        $data->setRefundAmount(null === $locked->getRefundAmount() ? null : (float) $locked->getRefundAmount());
        $data->setProductSaleElementsId($locked->getProductSaleElementsId());
    }

    /**
     * What was received of a return is restocked and refunded when it moves
     * to received. Past that point - or once it can no longer get there -
     * changing a quantity, the condition or the resellable flag of a line
     * would leave the stock and the refund behind.
     */
    private function assertTheLineMayStillChange(OrderReturn $return, OrderReturnLine $locked, OrderReturnLineResource $data): void
    {
        $statusCode = $return->getStatusCode();

        if (null !== $statusCode && $this->stateMachine->canStillReach($statusCode, OrderReturnStatus::CODE_RECEIVED)) {
            return;
        }

        if (self::sameQuantity($data->getQuantity(), (float) $locked->getQuantity())
            && self::sameQuantity($data->getQuantityReceived() ?? 0.0, (float) $locked->getQuantityReceived())
            && $data->getReceivedCondition() === $locked->getReceivedCondition()
            && (bool) $data->getResellable() === (bool) $locked->getResellable()) {
            return;
        }

        throw new UnprocessableEntityHttpException(\sprintf('The lines of a return in status "%s" can no longer be changed.', $statusCode ?? 'none'));
    }

    private static function sameQuantity(float $one, float $other): bool
    {
        return abs($one - $other) <= ReturnEligibilityChecker::QUANTITY_TOLERANCE;
    }

    /**
     * Raising or lowering a line goes through the gate a creation goes
     * through, on the order and the order product read under their locks.
     */
    private function assertTheNewQuantityIsReturnable(int $orderId, OrderReturnLine $locked, float $quantity, ConnectionInterface $connection): void
    {
        $order = OrderQuery::create()->findPk($orderId, $connection);
        $orderProduct = OrderProductQuery::create()->findPk((int) $locked->getOrderProductId(), $connection);
        $customer = $order?->getCustomer($connection);

        if (!$order instanceof Order || !$orderProduct instanceof OrderProduct || !$customer instanceof Customer) {
            throw new UnprocessableEntityHttpException('The return line is not attached to an order of a customer.');
        }

        $this->eligibility->assertReturnable(
            $order,
            $customer,
            $orderProduct,
            $quantity,
            excludeLineId: (int) $locked->getId(),
        );
    }
}

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
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderStatus as OrderStatusResource;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusChangeConflictException;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Payment\Exception\CancellationNeedsCaptureRightException;
use Thelia\Domain\Payment\Exception\CancellationWaitsForCaptureException;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;

/**
 * A status written through the admin API takes the same road as one chosen in
 * the back office: the ORDER_UPDATE_STATUS event, with its transition guard,
 * its stock and invoice handling and its configured actions. The other fields
 * are persisted as usual, the status column itself is never written directly.
 */
final readonly class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private EventDispatcherInterface $eventDispatcher,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderResource || !isset($uriVariables['id'])) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $order = OrderQuery::create()->findPk((int) $uriVariables['id']);

        if (null === $order) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $this->assertPaymentNamesUntouched($order, $data);

        $requestedStatusId = $this->requestedStatusId($data, $context['previous_data'] ?? null, $order->getStatusId());

        if (null === $requestedStatusId) {
            return $this->persistUnderTheLockOfTheOrder($data, $operation, $uriVariables, $context);
        }

        // The status moves through the event, as from the back office, and the other fields
        // of the request are written by the core in the transaction that writes the status:
        // after the guards ahead of the core and the transition graph have let the change
        // through, under the row lock of the order taken as the first statement of that
        // transaction, so that no other request acts on the order between the two (the
        // settlement of a payment part waits, then finds the order cancelled). The event
        // itself is not dispatched inside a transaction of this request: its listeners call
        // payment modules and write the payment journal under its own lock, which a
        // transaction held open around them would outlive.
        $resource = null;
        $event = (new OrderEvent($order))
            ->setStatus($requestedStatusId)
            ->writeWithStatus(function (int $lockedStatusId) use ($data, $operation, $uriVariables, $context, &$resource): void {
                // The status column is moved by the core only: the one written back with the
                // other fields is the one read under the lock.
                $data->setOrderStatus((new OrderStatusResource())->setId($lockedStatusId));
                $resource = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            });

        try {
            $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } catch (OrderStatusTransitionRefusedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (CancellationNeedsCaptureRightException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (CancellationWaitsForCaptureException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\InvalidArgumentException $exception) {
            // The core refuses to move an order whose row is gone under the lock.
            if (!OrderQuery::create()->filterById((int) $uriVariables['id'])->exists()) {
                throw new NotFoundHttpException('No such order.', $exception);
            }

            throw $exception;
        }

        // A listener ahead of the core stopped the change without refusing it: the status
        // stays, and the other fields are written as for a request that leaves it.
        if (null === $resource) {
            return $this->persistUnderTheLockOfTheOrder($data, $operation, $uriVariables, $context);
        }

        // The order already held the requested status under the lock: no transition.
        if (null === $event->getPreviousStatusId()) {
            return $resource;
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $event->getOrder(),
            context: $operation->getNormalizationContext() ?? [],
        );
    }

    /**
     * Every write of an order goes down the locked road, whatever the request says of the
     * status: the resource carries the status the provider read, which may be older than
     * the row, and the persistence writes it back. The lock is the first statement of the
     * transaction, so that what is read afterwards is what another request committed
     * before the lock was granted.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function persistUnderTheLockOfTheOrder(OrderResource $data, Operation $operation, array $uriVariables, array $context): mixed
    {
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $lockedStatusId = OrderQuery::create()->lockStatusIdForUpdate((int) $uriVariables['id'], $connection) ?? throw new NotFoundHttpException('No such order.');

            // The status written back is always the one read under the lock: the column itself is
            // moved through the event only, and a status another request committed since the
            // provider read the order is not undone by a request that never spoke of the status.
            $data->setOrderStatus((new OrderStatusResource())->setId($lockedStatusId));
            $resource = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            // Another request took a lock this one needed and the database broke the tie:
            // nothing of this change stands, and asked again it is judged on what the other left.
            if (!$throwable instanceof OrderStatusChangeConflictException && LockConflictDetector::isLockConflict($throwable)) {
                throw new OrderStatusChangeConflictException($throwable);
            }

            throw $throwable;
        }

        return $resource;
    }

    /**
     * Once money moved on the order, its payment journal names the provider reference and
     * the module of each movement: a refund or a release goes to the reference of the order,
     * through its module. Changing either by hand would send them to another order's
     * transaction, so they are refused; an order with no journal line takes them as before.
     */
    private function assertPaymentNamesUntouched(Order $order, OrderResource $data): void
    {
        $referenceChanged = self::referenceOf($data->getTransactionRef()) !== self::referenceOf($order->getTransactionRef());
        $moduleChanged = $data->getPaymentModule()?->getId() !== $order->getPaymentModuleId();

        if (!$referenceChanged && !$moduleChanged) {
            return;
        }

        if (!OrderPaymentTransactionQuery::create()->filterByOrderId((int) $order->getId())->exists()) {
            return;
        }

        throw new UnprocessableEntityHttpException(\sprintf('Order %s has a payment journal: its transaction reference and payment module are no longer changed by hand.', (string) $order->getRef()));
    }

    /**
     * No reference and an empty one are the same: neither names a transaction.
     */
    private static function referenceOf(?string $transactionRef): ?string
    {
        return null === $transactionRef || '' === trim($transactionRef) ? null : $transactionRef;
    }

    /**
     * The status the client asked for, null when the request leaves it as it was: the status
     * of the resource is compared with the one the provider read before the request was
     * applied to it (API Platform's previous data). Called without it, a status that differs
     * from the one of the order read just before is taken as asked for.
     */
    private function requestedStatusId(OrderResource $data, mixed $previousData, ?int $statusIdRead): ?int
    {
        if (!(new \ReflectionProperty($data, 'orderStatus'))->isInitialized($data)) {
            return null;
        }

        $statusId = $data->getOrderStatus()->getId();

        if ($previousData instanceof OrderResource && (new \ReflectionProperty($previousData, 'orderStatus'))->isInitialized($previousData)) {
            $statusIdRead = $previousData->getOrderStatus()->getId();
        }

        return $statusId === $statusIdRead ? null : $statusId;
    }
}

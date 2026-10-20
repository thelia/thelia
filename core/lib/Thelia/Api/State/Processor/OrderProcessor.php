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
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Domain\Payment\Exception\CancellationNeedsCaptureRightException;
use Thelia\Domain\Payment\Service\AuthorizedOrderCancellationGuard;
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
        private OrderStatusTransitionGuard $transitionGuard,
        private AuthorizedOrderCancellationGuard $cancellationGuard,
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

        // Every write of an order goes down the locked road, whatever the request says of the
        // status: the resource carries the status the provider read, which may be older than
        // the row, and the persistence writes it back. A transition the graph refuses is
        // refused under that lock, before anything of the request is written.
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $lockedStatusId = OrderQuery::create()->lockStatusIdForUpdate((int) $uriVariables['id'], $connection) ?? throw new NotFoundHttpException('No such order.');
            $requestedStatusId = $this->requestedStatusId($data, $context['previous_data'] ?? null, $order->getStatusId());

            if (null !== $requestedStatusId && $requestedStatusId !== $lockedStatusId) {
                $order->setStatusId($lockedStatusId);
                $this->transitionGuard->assertAllowed($order, $requestedStatusId);
                $this->cancellationGuard->assertMayMoveTo($order, $requestedStatusId);
            }

            // The status written back is always the one read under the lock: the column itself is
            // moved through the event only, and a status another request committed since the
            // provider read the order is not undone by a request that never spoke of the status.
            $data->setOrderStatus((new OrderStatusResource())->setId($lockedStatusId));
            $resource = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            $connection->commit();
        } catch (OrderStatusTransitionRefusedException $exception) {
            $connection->rollBack();

            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (CancellationNeedsCaptureRightException $exception) {
            $connection->rollBack();

            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        if (null === $requestedStatusId || $requestedStatusId === $lockedStatusId) {
            return $resource;
        }

        // The status moves through the event once the other fields are committed, as the back
        // office does: its listeners call payment modules and write the payment journal under
        // its own lock, which a transaction held open around them would outlive. The status
        // change itself rereads the status under a row lock and checks the transition again.
        $order->reload();
        $event = (new OrderEvent($order))->setStatus($requestedStatusId);

        try {
            $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } catch (OrderStatusTransitionRefusedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (CancellationNeedsCaptureRightException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $event->getOrder(),
            context: $operation->getNormalizationContext() ?? [],
        );
    }

    /**
     * Once money moved on the order, its payment journal names the provider reference and
     * the module of each movement: a refund or a release goes to the reference of the order,
     * through its module. Changing either by hand would send them to another order's
     * transaction, so they are refused; an order with no journal line takes them as before.
     */
    private function assertPaymentNamesUntouched(Order $order, OrderResource $data): void
    {
        $referenceChanged = $data->getTransactionRef() !== $order->getTransactionRef();
        $moduleChanged = (new \ReflectionProperty($data, 'paymentModule'))->isInitialized($data)
            && $data->getPaymentModule()?->getId() !== $order->getPaymentModuleId();

        if (!$referenceChanged && !$moduleChanged) {
            return;
        }

        if (!OrderPaymentTransactionQuery::create()->filterByOrderId((int) $order->getId())->exists()) {
            return;
        }

        throw new UnprocessableEntityHttpException(\sprintf('Order %s has a payment journal: its transaction reference and payment module are no longer changed by hand.', (string) $order->getRef()));
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

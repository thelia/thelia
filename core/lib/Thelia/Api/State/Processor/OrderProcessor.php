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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderStatus as OrderStatusResource;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Model\Map\OrderTableMap;
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

        // Every write of an order goes down the locked road, whatever the request says of the
        // status: the resource carries the status the provider read, which may be older than
        // the row, and the persistence writes it back. One transaction around the write and
        // the event: a refused transition must not leave the other fields of the request
        // written while the client is told the write failed.
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $event = null;

        try {
            // The first statement of the transaction: the listeners of the status event (the
            // release of the payment parts asks the modules to give back what they hold) then
            // read what another request committed before the lock was granted.
            $lockedStatusId = OrderQuery::create()->lockStatusIdForUpdate((int) $uriVariables['id'], $connection) ?? throw new NotFoundHttpException('No such order.');
            $requestedStatusId = $this->requestedStatusId($data, $context['previous_data'] ?? null, $order->getStatusId());

            // The status written back is always the one read under the lock: the column itself is
            // moved through the event only, and a status another request committed since the
            // provider read the order is not undone by a request that never spoke of the status.
            $data->setOrderStatus((new OrderStatusResource())->setId($lockedStatusId));
            $resource = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            if (null !== $requestedStatusId && $requestedStatusId !== $lockedStatusId) {
                $order->reload();
                $event = (new OrderEvent($order))->setStatus($requestedStatusId);
                $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
            }

            $connection->commit();
        } catch (OrderStatusTransitionRefusedException $exception) {
            $connection->rollBack();

            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        if (null === $event) {
            return $resource;
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $event->getOrder(),
            context: $operation->getNormalizationContext() ?? [],
        );
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

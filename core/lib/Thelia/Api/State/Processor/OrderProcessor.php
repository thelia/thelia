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
        $requestedStatusId = $this->requestedStatusId($data);

        if (null === $order || null === $requestedStatusId || $requestedStatusId === $order->getStatusId()) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Persist everything but the status, then move the status through the event.
        // One transaction around both: a refused transition must not leave the other
        // fields of the request written while the client is told the write failed.
        $data->setOrderStatus((new OrderStatusResource())->setId($order->getStatusId()));

        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            $order->reload();
            $event = (new OrderEvent($order))->setStatus($requestedStatusId);
            $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

            $connection->commit();
        } catch (OrderStatusTransitionRefusedException $exception) {
            $connection->rollBack();

            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $event->getOrder(),
            context: $operation->getNormalizationContext() ?? [],
        );
    }

    private function requestedStatusId(OrderResource $data): ?int
    {
        if (!(new \ReflectionProperty($data, 'orderStatus'))->isInitialized($data)) {
            return null;
        }

        return $data->getOrderStatus()->getId();
    }
}

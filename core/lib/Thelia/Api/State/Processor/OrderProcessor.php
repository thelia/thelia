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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        $requestedStatusId = $this->requestedStatusId($data);

        if (null === $order || null === $requestedStatusId || $requestedStatusId === $order->getStatusId()) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // A transition the graph refuses is refused before anything of the request is
        // written. The other fields are then persisted, and the status moved through the
        // event once they are committed, as the back office does: its listeners call
        // payment modules and write the payment journal under its own lock, which a
        // transaction held open around them would outlive.
        try {
            $this->transitionGuard->assertAllowed($order, $requestedStatusId);
            $this->cancellationGuard->assertMayMoveTo($order, $requestedStatusId);
        } catch (OrderStatusTransitionRefusedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (CancellationNeedsCaptureRightException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        $data->setOrderStatus((new OrderStatusResource())->setId($order->getStatusId()));
        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

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

    private function requestedStatusId(OrderResource $data): ?int
    {
        if (!(new \ReflectionProperty($data, 'orderStatus'))->isInitialized($data)) {
            return null;
        }

        return $data->getOrderStatus()->getId();
    }
}

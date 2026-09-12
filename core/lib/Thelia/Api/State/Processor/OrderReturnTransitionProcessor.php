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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\OrderReturn as OrderReturnResource;
use Thelia\Api\Resource\OrderReturnTransitionInput;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatusQuery;

/**
 * Moves a return to a new status through the return state machine: it never
 * writes the status column directly, it dispatches the domain event that
 * validates the transition, restocks, recomputes the refund and notifies the
 * customer, then returns the updated return.
 */
final readonly class OrderReturnTransitionProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $return = OrderReturnQuery::create()->findPk((int) ($uriVariables['id'] ?? 0));

        if (null === $return) {
            throw new NotFoundHttpException('Return not found.');
        }

        if (!$data instanceof OrderReturnTransitionInput || null === $data->statusCode) {
            throw new UnprocessableEntityHttpException('A target status code is required.');
        }

        $status = OrderReturnStatusQuery::create()->findOneByCodeCached($data->statusCode);

        if (null === $status) {
            throw new UnprocessableEntityHttpException(\sprintf('Unknown return status "%s".', $data->statusCode));
        }

        $event = new OrderReturnEvent($return);
        $event->setTargetStatusId((int) $status->getId())->setRefusalReason($data->refusalReason);

        try {
            $this->dispatcher->dispatch($event, TheliaEvents::ORDER_RETURN_UPDATE_STATUS);
        } catch (ReturnNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderReturnResource::class,
            propelModel: $event->getOrderReturn(),
            context: $context,
        );
    }
}

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\OrderShipment as OrderShipmentResource;
use Thelia\Api\Resource\OrderShipmentTransitionInput;
use Thelia\Domain\Shipping\Exception\ShipmentNotAllowedException;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\OrderShipmentQuery;

/**
 * Ships or cancels a draft through the manager, which is what keeps the order in
 * step with its parcels; the status column is never written from here.
 */
final readonly class OrderShipmentTransitionProcessor implements ProcessorInterface
{
    public function __construct(
        private OrderShipmentManager $shipmentManager,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $shipment = OrderShipmentQuery::create()->findPk((int) ($uriVariables['id'] ?? 0));

        if (null === $shipment) {
            throw new NotFoundHttpException('Shipment not found.');
        }

        if (!$data instanceof OrderShipmentTransitionInput || null === $data->action) {
            throw new UnprocessableEntityHttpException('An action is required: ship or cancel.');
        }

        try {
            $shipment = match ($data->action) {
                OrderShipmentTransitionInput::ACTION_SHIP => $this->shipmentManager->ship($shipment, $data->deliveryRef),
                OrderShipmentTransitionInput::ACTION_CANCEL => $this->shipmentManager->cancel($shipment),
                default => throw new UnprocessableEntityHttpException(\sprintf('Unknown action "%s".', $data->action)),
            };
        } catch (ShipmentNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->transformer->modelToResource(
            resourceClass: OrderShipmentResource::class,
            propelModel: $shipment,
            context: $context,
        );
    }
}

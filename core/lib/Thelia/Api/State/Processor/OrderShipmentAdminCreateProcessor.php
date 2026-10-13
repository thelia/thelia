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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\OrderShipment as OrderShipmentResource;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Exception\ShipmentNotAllowedException;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\OrderQuery;

/**
 * A shipment opened by the merchant: the lines and quantities come from the body,
 * everything else (reference, status, carrier name, what is left to ship) is the
 * manager's to decide. With `ship` set, the parcel leaves at once.
 */
final readonly class OrderShipmentAdminCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private OrderShipmentManager $shipmentManager,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrderShipmentResource) {
            throw new UnprocessableEntityHttpException('A shipment is expected.');
        }

        $order = OrderQuery::create()->findPk((int) $data->getOrder()?->getId());

        if (null === $order) {
            throw new UnprocessableEntityHttpException('The order does not exist.');
        }

        $lines = [];

        foreach ($data->getOrderShipmentItems() as $item) {
            $lines[] = new ShipmentLine((int) $item->getOrderProduct()->getId(), (float) $item->getQuantity());
        }

        try {
            $shipment = $this->shipmentManager->open(
                $order,
                $lines,
                $data->getDeliveryModule()?->getId(),
                $data->getDeliveryRef(),
            );

            if ($data->isShip()) {
                $shipment = $this->shipmentManager->ship($shipment);
            }
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

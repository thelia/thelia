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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\OrderShipmentItemTableMap;

/**
 * One line of a shipment: which order line, and how much of it the parcel carries.
 * Written only as part of a shipment being opened.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/order_shipment_items',
        ),
        new Get(
            uriTemplate: '/admin/order_shipment_items/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
class OrderShipmentItem implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_shipment_item:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_shipment_item:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_shipment_item:write';
    public const GROUP_FRONT_READ = 'front:order_shipment_item:read';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        OrderShipment::GROUP_ADMIN_READ_SINGLE,
        OrderShipment::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Relation(targetResource: OrderProduct::class)]
    #[NotBlank(groups: [OrderShipment::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        OrderShipment::GROUP_ADMIN_READ_SINGLE,
        OrderShipment::GROUP_ADMIN_WRITE,
        OrderShipment::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public OrderProduct $orderProduct;

    #[NotBlank(groups: [OrderShipment::GROUP_ADMIN_WRITE])]
    #[Positive(groups: [OrderShipment::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        OrderShipment::GROUP_ADMIN_READ_SINGLE,
        OrderShipment::GROUP_ADMIN_WRITE,
        OrderShipment::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public float $quantity;

    #[Relation(targetResource: OrderShipment::class)]
    #[Column(propelSetter: 'setOrderShipmentId')]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?OrderShipment $orderShipment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOrderProduct(): OrderProduct
    {
        return $this->orderProduct;
    }

    public function setOrderProduct(OrderProduct $orderProduct): self
    {
        $this->orderProduct = $orderProduct;

        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOrderShipment(): ?OrderShipment
    {
        return $this->orderShipment;
    }

    public function setOrderShipment(?OrderShipment $orderShipment): self
    {
        $this->orderShipment = $orderShipment;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderShipmentItemTableMap();
    }
}

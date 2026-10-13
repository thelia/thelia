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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\OrderShipmentAdminCreateProcessor;
use Thelia\Api\State\Processor\OrderShipmentTransitionProcessor;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Map\OrderShipmentTableMap;

/**
 * A parcel, or a set of parcels leaving together, carrying part or all of an order.
 *
 * Opened by the merchant as a draft with the lines it carries, then shipped or
 * cancelled through the transition operation; never written column by column, so
 * what left can never exceed what was ordered. The customer reads the shipments
 * of their own orders, each with its tracking number and carrier page.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/order_shipments',
        ),
        new Get(
            uriTemplate: '/admin/order_shipments/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Post(
            uriTemplate: '/admin/order_shipments',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            processor: OrderShipmentAdminCreateProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
        new Post(
            uriTemplate: '/admin/order_shipments/{id}/transition',
            status: 200,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_TRANSITION]],
            input: OrderShipmentTransitionInput::class,
            read: false,
            processor: OrderShipmentTransitionProcessor::class,
            extraProperties: ['admin_access' => AccessManager::UPDATE],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/order_shipments',
            extraProperties: ['usesForCustomer' => ['order']],
        ),
        new Get(
            uriTemplate: '/front/account/order_shipments/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'object.order.customer.getId() == user.getId()',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'ref' => 'partial',
        'status' => 'exact',
        'deliveryRef' => 'partial',
        'order.id' => 'exact',
        'order.ref' => 'partial',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'createdAt',
        'shippedAt',
    ],
)]
class OrderShipment implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_shipment:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_shipment:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_shipment:write';
    public const GROUP_ADMIN_TRANSITION = 'admin:order_shipment:transition';
    public const GROUP_FRONT_READ = 'front:order_shipment:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:order_shipment:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $ref = null;

    /**
     * draft, shipped or cancelled: set by the transition operation, never by a write.
     */
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $status = null;

    #[Relation(targetResource: Order::class)]
    #[Column(propelSetter: 'setOrderId')]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?Order $order = null;

    /**
     * The carrier the parcel leaves with, the order's by default. Null once that
     * module is gone: the name it carried stays in deliveryModuleTitle.
     */
    #[Relation(targetResource: Module::class)]
    #[Column(propelSetter: 'setDeliveryModuleId')]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?Module $deliveryModule = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $deliveryModuleTitle = null;

    /**
     * The tracking number the carrier gave for this parcel.
     */
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $deliveryRef = null;

    /**
     * The carrier page following this parcel, built from its tracking number and
     * carrier the way the order's own is. Computed on read, never stored.
     */
    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?string $trackingUrl = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?\DateTime $shippedAt = null;

    #[Relation(targetResource: OrderShipmentItem::class)]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public array $orderShipmentItems = [];

    /**
     * Whether the parcel leaves as soon as it is opened, the common case of a merchant
     * who composes the parcel with the label in hand. Read by the creation only.
     */
    #[ApiProperty(readable: false, writable: true)]
    #[Groups([self::GROUP_ADMIN_WRITE])]
    public bool $ship = false;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Order::GROUP_ADMIN_READ_SINGLE,
        Order::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getDeliveryModule(): ?Module
    {
        return $this->deliveryModule;
    }

    public function setDeliveryModule(?Module $deliveryModule): self
    {
        $this->deliveryModule = $deliveryModule;

        return $this;
    }

    public function getDeliveryModuleTitle(): ?string
    {
        return $this->deliveryModuleTitle;
    }

    public function setDeliveryModuleTitle(?string $deliveryModuleTitle): self
    {
        $this->deliveryModuleTitle = $deliveryModuleTitle;

        return $this;
    }

    public function getDeliveryRef(): ?string
    {
        return $this->deliveryRef;
    }

    public function setDeliveryRef(?string $deliveryRef): self
    {
        $this->deliveryRef = $deliveryRef;

        return $this;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): self
    {
        $this->trackingUrl = $trackingUrl;

        return $this;
    }

    public function getShippedAt(): ?\DateTime
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTime $shippedAt): self
    {
        $this->shippedAt = $shippedAt;

        return $this;
    }

    /**
     * @return list<OrderShipmentItem>
     */
    public function getOrderShipmentItems(): array
    {
        return $this->orderShipmentItems;
    }

    /**
     * @param list<OrderShipmentItem> $orderShipmentItems
     */
    public function setOrderShipmentItems(array $orderShipmentItems): self
    {
        $this->orderShipmentItems = $orderShipmentItems;

        return $this;
    }

    public function isShip(): bool
    {
        return $this->ship;
    }

    public function setShip(bool $ship): self
    {
        $this->ship = $ship;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderShipmentTableMap();
    }
}

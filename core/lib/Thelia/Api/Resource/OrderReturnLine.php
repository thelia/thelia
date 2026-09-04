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
use ApiPlatform\Metadata\Patch;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\OrderReturnLineTableMap;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/order_return_lines',
        ),
        new Get(
            uriTemplate: '/admin/order_return_lines/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Patch(
            uriTemplate: '/admin/order_return_lines/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
class OrderReturnLine implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_return_line:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_return_line:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_return_line:write';
    public const GROUP_FRONT_READ = 'front:order_return_line:read';
    public const GROUP_FRONT_WRITE = 'front:order_return_line:write';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Relation(targetResource: OrderProduct::class)]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE, self::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_WRITE,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_ADMIN_WRITE,
        OrderReturn::GROUP_FRONT_READ,
        OrderReturn::GROUP_FRONT_WRITE,
    ])]
    public OrderProduct $orderProduct;

    #[NotBlank(groups: [self::GROUP_FRONT_WRITE, self::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_WRITE,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_ADMIN_WRITE,
        OrderReturn::GROUP_FRONT_READ,
        OrderReturn::GROUP_FRONT_WRITE,
    ])]
    public float $quantity;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_ADMIN_WRITE,
    ])]
    public ?float $quantityReceived = 0.0;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_ADMIN_WRITE,
    ])]
    public ?string $receivedCondition = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_ADMIN_WRITE,
    ])]
    public ?bool $resellable = false;

    /**
     * The sale element to restock, snapshot from the order product at creation. Read only.
     */
    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?int $productSaleElementsId = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        OrderReturn::GROUP_ADMIN_READ,
        OrderReturn::GROUP_FRONT_READ,
    ])]
    public ?float $refundAmount = null;

    #[Relation(targetResource: OrderReturn::class)]
    #[Column(propelSetter: 'setOrderReturnId')]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public ?OrderReturn $orderReturn = null;

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

    public function getQuantityReceived(): ?float
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(?float $quantityReceived): self
    {
        $this->quantityReceived = $quantityReceived;

        return $this;
    }

    public function getReceivedCondition(): ?string
    {
        return $this->receivedCondition;
    }

    public function setReceivedCondition(?string $receivedCondition): self
    {
        $this->receivedCondition = $receivedCondition;

        return $this;
    }

    public function getResellable(): ?bool
    {
        return $this->resellable;
    }

    public function setResellable(?bool $resellable): self
    {
        $this->resellable = $resellable;

        return $this;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): self
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    public function getRefundAmount(): ?float
    {
        return $this->refundAmount;
    }

    public function setRefundAmount(?float $refundAmount): self
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    public function getOrderReturn(): ?OrderReturn
    {
        return $this->orderReturn;
    }

    public function setOrderReturn(?OrderReturn $orderReturn): self
    {
        $this->orderReturn = $orderReturn;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderReturnLineTableMap();
    }
}

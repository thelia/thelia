<?php

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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\OrderProductTaxTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_product_taxes'
        ),
        new Get(
            uriTemplate: '/admin/order_product_taxes/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_product_taxes/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/order_product_taxes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_product_taxes/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class OrderProductTax implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_product_tax:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_product_tax:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_product_tax:write';

    #[Groups([self::GROUP_ADMIN_READ, OrderProduct::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_READ_SINGLE, OrderProduct::GROUP_FRONT_READ_SINGLE])]
    public ?int $id = null;

    #[Relation(targetResource: OrderProduct::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public OrderProduct $orderProduct;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderProduct::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public string $title;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderProduct::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_WRITE])]
    public ?string $description;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderProduct::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public float $amount;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderProduct::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_WRITE])]
    public ?float $promoAmount;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getAmount(): float
    {
        return round($this->amount, 2);
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPromoAmount(): ?float
    {
        return round($this->promoAmount, 2);
    }

    public function setPromoAmount(?float $promoAmount): self
    {
        $this->promoAmount = $promoAmount;

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
        return new OrderProductTaxTableMap();
    }
}

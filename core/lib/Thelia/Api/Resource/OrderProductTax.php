<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_product_taxes'
        ),
        new Get(
            uriTemplate: '/admin/order_product_taxes/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_product_taxes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_product_taxes/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderProductTax extends AbstractPropelResource
{
    public const GROUP_READ = 'order_product_tax:read';
    public const GROUP_READ_SINGLE = 'order_product_tax:read:single';
    public const GROUP_WRITE = 'order_product_tax:write';

    #[Groups([self::GROUP_READ, OrderProduct::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public OrderProduct $orderProduct;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,OrderProduct::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public string $title;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,OrderProduct::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public ?string $description;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,OrderProduct::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public float $amount;

    #[Groups([self::GROUP_READ,self::GROUP_WRITE,OrderProduct::GROUP_READ_SINGLE,Order::GROUP_READ_SINGLE])]
    public ?float $promoAmount;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): OrderProductTax
    {
        $this->id = $id;
        return $this;
    }

    public function getOrderProduct(): OrderProduct
    {
        return $this->orderProduct;
    }

    public function setOrderProduct(OrderProduct $orderProduct): OrderProductTax
    {
        $this->orderProduct = $orderProduct;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): OrderProductTax
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): OrderProductTax
    {
        $this->description = $description;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): OrderProductTax
    {
        $this->amount = $amount;
        return $this;
    }

    public function getPromoAmount(): ?float
    {
        return $this->promoAmount;
    }

    public function setPromoAmount(?float $promoAmount): OrderProductTax
    {
        $this->promoAmount = $promoAmount;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): OrderProductTax
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): OrderProductTax
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderProduct::class;
    }
}

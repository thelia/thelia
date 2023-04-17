<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order'
        ),
        new GetCollection(
            uriTemplate: '/admin/order'
        ),
        new Get(
            uriTemplate: '/admin/order/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderProductTax extends AbstractPropelResource
{
    public const GROUP_READ = 'customer:read';
    public const GROUP_READ_SINGLE = 'customer:read:single';
    public const GROUP_WRITE = 'customer:write';

    #[Groups([self::GROUP_READ,OrderProduct::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ])]
    public OrderProduct $orderProduct;

    #[Groups([self::GROUP_READ])]
    public string $title;

    #[Groups([self::GROUP_READ])]
    public ?string $description;

    #[Groups([self::GROUP_READ])]
    public float $amount;

    #[Groups([self::GROUP_READ])]
    public ?float $promoAmount;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getOrderProduct(): OrderProduct
    {
        return $this->orderProduct;
    }

    public function setOrderProduct(OrderProduct $orderProduct): void
    {
        $this->orderProduct = $orderProduct;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getPromoAmount(): ?float
    {
        return $this->promoAmount;
    }

    public function setPromoAmount(?float $promoAmount): void
    {
        $this->promoAmount = $promoAmount;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderProduct::class;
    }
}

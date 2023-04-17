<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_sale_elements'
        ),
        new GetCollection(
            uriTemplate: '/admin/product_sale_elements'
        ),
        new Get(
            uriTemplate: '/admin/product_sale_elements/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/product_sale_elements/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/product_sale_elements/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]

class ProductSaleElements extends AbstractPropelResource
{

    public const GROUP_READ = 'product_sale_elements:read';
    public const GROUP_READ_SINGLE = 'product_sale_elements:read:single';
    public const GROUP_WRITE = 'product_sale_elements:write';

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_READ])]
    public ?Product $product;

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public string $ref;

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public int $quantity;

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public ?bool $promo;

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public ?bool $newness;

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ])]
    public ?float $weight;

    #[Groups([self::GROUP_READ])]
    public ?bool $isDefault;

    #[Groups([self::GROUP_READ])]
    public ?string $eanCode;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): void
    {
        $this->ref = $ref;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPromo(): ?bool
    {
        return $this->promo;
    }

    public function setPromo(?bool $promo): void
    {
        $this->promo = $promo;
    }

    public function getNewness(): ?bool
    {
        return $this->newness;
    }

    public function setNewness(?bool $newness): void
    {
        $this->newness = $newness;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): void
    {
        $this->eanCode = $eanCode;
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
        return \Thelia\Model\ProductSaleElements::class;
    }
}

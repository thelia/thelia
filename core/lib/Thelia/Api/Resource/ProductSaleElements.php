<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
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
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): ProductSaleElements
    {
        $this->id = $id;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): ProductSaleElements
    {
        $this->product = $product;
        return $this;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): ProductSaleElements
    {
        $this->ref = $ref;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): ProductSaleElements
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPromo(): ?bool
    {
        return $this->promo;
    }

    public function setPromo(?bool $promo): ProductSaleElements
    {
        $this->promo = $promo;
        return $this;
    }

    public function getNewness(): ?bool
    {
        return $this->newness;
    }

    public function setNewness(?bool $newness): ProductSaleElements
    {
        $this->newness = $newness;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): ProductSaleElements
    {
        $this->weight = $weight;
        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): ProductSaleElements
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): ProductSaleElements
    {
        $this->eanCode = $eanCode;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): ProductSaleElements
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): ProductSaleElements
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\ProductSaleElements::class;
    }
}

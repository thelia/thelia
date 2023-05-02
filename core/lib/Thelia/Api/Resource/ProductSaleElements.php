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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Collection\Collection;
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

    #[Groups([self::GROUP_READ, CartItem::GROUP_READ, ProductPrice::GROUP_READ, Product::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Product $product;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CartItem::GROUP_READ])]
    public string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CartItem::GROUP_READ])]
    public int $quantity;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CartItem::GROUP_READ])]
    public ?bool $promo;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CartItem::GROUP_READ])]
    public ?bool $newness;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, CartItem::GROUP_READ])]
    public ?float $weight;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?bool $isDefault;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $eanCode;

    #[Relation(targetResource: ProductPrice::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    public Collection $productPrices;

    #[Relation(targetResource: AttributeCombination::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    public Collection $attributeCombinations;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPromo(): ?bool
    {
        return $this->promo;
    }

    public function setPromo(?bool $promo): self
    {
        $this->promo = $promo;

        return $this;
    }

    public function getNewness(): ?bool
    {
        return $this->newness;
    }

    public function setNewness(?bool $newness): self
    {
        $this->newness = $newness;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getEanCode(): ?string
    {
        return $this->eanCode;
    }

    public function setEanCode(?string $eanCode): self
    {
        $this->eanCode = $eanCode;

        return $this;
    }

    public function getAttributeCombinations(): Collection
    {
        return $this->attributeCombinations;
    }

    public function setAttributeCombinations(Collection $attributeCombinations): ProductSaleElements
    {
        $this->attributeCombinations = $attributeCombinations;
        return $this;
    }

    public function getProductPrices(): Collection
    {
        return $this->productPrices;
    }

    public function setProductPrices(array $productPrices): self
    {
        $this->productPrices = new Collection($productPrices);
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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\ProductSaleElements::class;
    }
}

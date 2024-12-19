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
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product_prices/{productSaleElements}/currencies/{currency}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[CompositeIdentifiers(['productSaleElements', 'currency'])]
class ProductPrice extends AbstractPropelResource
{
    public const GROUP_READ = 'product_price:read';
    public const GROUP_READ_SINGLE = 'product_price:read:single';
    public const GROUP_WRITE = 'product_price:write';

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_READ])]
    public ProductSaleElements $productSaleElements;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_READ, Product::GROUP_READ_SINGLE, ProductSaleElements::GROUP_WRITE, Product::GROUP_WRITE])]
    public Currency $currency;

    #[Groups([self::GROUP_READ, Product::GROUP_READ_SINGLE, ProductSaleElements::GROUP_WRITE, Product::GROUP_WRITE])]
    public float $price;

    #[Groups([self::GROUP_READ, Product::GROUP_READ_SINGLE, ProductSaleElements::GROUP_WRITE, Product::GROUP_WRITE])]
    public float $promoPrice;

    #[Groups([self::GROUP_READ, Product::GROUP_WRITE])]
    public ?bool $fromDefaultCurrency = true;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getProductSaleElements(): ProductSaleElements
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(ProductSaleElements $productSaleElements): self
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPromoPrice(): float
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(float $promoPrice): self
    {
        $this->promoPrice = $promoPrice;

        return $this;
    }

    public function getFromDefaultCurrency(): ?bool
    {
        return $this->fromDefaultCurrency;
    }

    public function setFromDefaultCurrency(?bool $fromDefaultCurrency): self
    {
        $this->fromDefaultCurrency = $fromDefaultCurrency;

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
        return \Thelia\Model\ProductPrice::class;
    }
}

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
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_product'
        ),
        new GetCollection(
            uriTemplate: '/admin/order_product'
        ),
        new Get(
            uriTemplate: '/admin/order_product/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_product/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_product/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderProduct extends AbstractPropelResource
{
    public const GROUP_READ = 'order_product:read';
    public const GROUP_READ_SINGLE = 'order_product:read:single';
    public const GROUP_WRITE = 'order_product:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?int $productSaleElementsId;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public string $productRef;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public string $productSaleElementsRef;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public int $quantity;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public float $price;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?float $promoPrice;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?float $weight;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?string $taxRuleTitle;

    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?string $taxRuleDescription;

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): void
    {
        $this->productSaleElementsId = $productSaleElementsId;
    }

    public function getTaxRuleTitle(): ?string
    {
        return $this->taxRuleTitle;
    }


    public function setTaxRuleTitle(?string $taxRuleTitle): void
    {
        $this->taxRuleTitle = $taxRuleTitle;
    }

    public function getTaxRuleDescription(): ?string
    {
        return $this->taxRuleDescription;
    }


    public function setTaxRuleDescription(?string $taxRuleDescription): void
    {
        $this->taxRuleDescription = $taxRuleDescription;
    }

    public function getProductRef(): string
    {
        return $this->productRef;
    }

    public function setProductRef(string $productRef): void
    {
        $this->productRef = $productRef;
    }

    public function getProductSaleElementsRef(): string
    {
        return $this->productSaleElementsRef;
    }

    public function setProductSaleElementsRef(string $productSaleElementsRef): void
    {
        $this->productSaleElementsRef = $productSaleElementsRef;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getPromoPrice(): ?int
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?int $promoPrice): void
    {
        $this->promoPrice = $promoPrice;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderProduct::class;
    }
}

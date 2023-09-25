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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\AttributeCombinationTableMap;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/attribute_combinations/{productSaleElements}/attribute_av/{attributeAv}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[CompositeIdentifiers(['productSaleElements', 'attributeAv'])]
class AttributeCombination extends AbstractPropelResource
{
    public const GROUP_READ = 'attribute_combination:read';
    public const GROUP_READ_SINGLE = 'attribute_combination:read:single';
    public const GROUP_WRITE = 'attribute_combination:write';

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public ProductSaleElements $productSaleElements;

    #[Relation(targetResource: Attribute::class)]
    #[Groups([self::GROUP_READ_SINGLE, Product::GROUP_READ_SINGLE, ProductSaleElements::GROUP_WRITE])]
    public Attribute $attribute;

    #[Relation(targetResource: AttributeAv::class)]
    #[Groups([self::GROUP_READ_SINGLE, Product::GROUP_READ_SINGLE, ProductSaleElements::GROUP_WRITE])]
    public AttributeAv $attributeAv;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

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

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(Attribute $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getAttributeAv(): AttributeAv
    {
        return $this->attributeAv;
    }

    public function setAttributeAv(AttributeAv $attributeAv): self
    {
        $this->attributeAv = $attributeAv;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

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
        return new AttributeCombinationTableMap();
    }
}

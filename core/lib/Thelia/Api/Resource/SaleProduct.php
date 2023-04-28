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
            uriTemplate: '/admin/sale_products/{sale}/products/{product}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[CompositeIdentifiers(['sale', 'product'])]
class SaleProduct extends AbstractPropelResource
{
    public const GROUP_READ = 'sale_product:read';
    public const GROUP_READ_SINGLE = 'sale_product:read:single';
    public const GROUP_WRITE = 'sale_product:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Sale::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Sale $sale;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Product $product;

    #[Relation(targetResource: AttributeAv::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public ?AttributeAv $attributeAv;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function setSale(Sale $sale): self
    {
        $this->sale = $sale;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getAttributeAv(): ?AttributeAv
    {
        return $this->attributeAv;
    }

    public function setAttributeAv(?AttributeAv $attributeAv): self
    {
        $this->attributeAv = $attributeAv;

        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\SaleProduct::class;
    }
}

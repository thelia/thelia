<?php

declare(strict_types=1);

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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\ProductSaleElementsProductImageTableMap;
use Thelia\Model\ProductSaleElementsQuery;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_sale_elements_product_image',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_FILE]],
            deserialize: false,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_sale_elements_product_image',
        ),
        new Get(
            uriTemplate: '/admin/product_sale_elements_product_image/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/product_sale_elements_product_image/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
        ),
        new Patch(
            uriTemplate: '/admin/product_sale_elements_product_image/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]],
        ),
        new Delete(
            uriTemplate: '/admin/product_sale_elements_product_image/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/product_sale_elements_product_image',
        ),
        new Get(
            uriTemplate: '/front/product_sale_elements_product_image/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'productSaleElements.product.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'productsaleelementsproductimage_productsaleelements.product_id',
        ],
        'productSaleElementsId' => 'exact',
        'productImageId' => 'exact',
    ],
)]
class ProductSaleElementsProductImage implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_sale_elements_product_image:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product_sale_elements_product_image:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product_sale_elements_product_image:write';
    public const GROUP_ADMIN_WRITE_FILE = 'admin:product_sale_elements_product_image:write_file';
    public const GROUP_ADMIN_WRITE_UPDATE = 'admin:product_sale_elements_product_image:write_update';
    public const GROUP_FRONT_READ = 'front:product_sale_elements_product_image:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product_sale_elements_product_image:read:single';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?int $productSaleElementsId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    #[Relation(targetResource: ProductSaleElements::class)]
    public ?ProductSaleElements $productSaleElements = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE_UPDATE])]
    public ?int $productImageId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $productId = null;

    public function getProductId(): ?int
    {
        if (null !== $this->productSaleElementsId) {
            $pse = ProductSaleElementsQuery::create()->findPk($this->productSaleElementsId);

            return $pse ? $pse->getProductId() : null;
        }

        return null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function setProductSaleElementsId(?int $productSaleElementsId): static
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    public function getProductImageId(): ?int
    {
        return $this->productImageId;
    }

    public function setProductImageId(?int $productImageId): static
    {
        $this->productImageId = $productImageId;

        return $this;
    }

    public function getProductSaleElements(): ?ProductSaleElements
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(?ProductSaleElements $productSaleElements): self
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ProductSaleElementsProductImageTableMap();
    }
}

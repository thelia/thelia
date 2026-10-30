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
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\ProductBundleItemProcessor;
use Thelia\Model\Map\ProductBundleItemTableMap;

/**
 * A component of a product bundle: a sale element, in a quantity, required or optional.
 *
 * The back office writes through ProductBundleItemProcessor, which calls
 * BundleCompositionService: the rules of a bundle (no bundle inside a bundle, no component
 * that is the bundle itself, bounds of the quantity) are the ones of the events of the back
 * office, and a refusal answers 422. The sale element is null once it has been deleted: the
 * line stays, and the bundle cannot be sold while it was required.
 *
 * The front lists the components a visitor can buy (see ProductBundleItemVisibilityExtension),
 * to build the composition it adds to the cart.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_bundle_items',
            processor: ProductBundleItemProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_bundle_items',
        ),
        new Get(
            uriTemplate: '/admin/product_bundle_items/{id}',
        ),
        new Put(
            uriTemplate: '/admin/product_bundle_items/{id}',
            processor: ProductBundleItemProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/product_bundle_items/{id}',
            processor: ProductBundleItemProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/product_bundle_items/{id}',
            processor: ProductBundleItemProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        // Public catalogue data, so explicitly open: the API refuses nothing by default outside /admin.
        new GetCollection(
            uriTemplate: '/front/product_bundle_items',
            security: 'true',
        ),
        new Get(
            uriTemplate: '/front/product_bundle_items/{id}',
            security: 'true',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'bundleProduct.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'product_bundle_item.bundle_product_id',
        ],
        'productSaleElements.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'product_bundle_item.product_sale_elements_id',
        ],
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'position',
    ],
)]
class ProductBundleItem implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_bundle_item:read';
    public const GROUP_ADMIN_WRITE = 'admin:product_bundle_item:write';
    public const GROUP_FRONT_READ = 'front:product_bundle_item:read';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public Product $bundleProduct;

    /**
     * Null once the sale element has been deleted.
     */
    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?ProductSaleElements $productSaleElements = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[ApiProperty(description: 'Quantity of the component in one bundle; the fixed quantity when minQuantity and maxQuantity are null')]
    #[Positive]
    public float $quantity = 1.0;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[ApiProperty(description: 'Lowest quantity a customer may choose')]
    #[PositiveOrZero]
    public ?float $minQuantity = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[ApiProperty(description: 'Highest quantity a customer may choose')]
    #[Positive]
    public ?float $maxQuantity = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[ApiProperty(description: 'A required component is always part of the bundle')]
    public bool $required = true;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getBundleProduct(): Product
    {
        return $this->bundleProduct;
    }

    public function setBundleProduct(Product $bundleProduct): self
    {
        $this->bundleProduct = $bundleProduct;

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

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getMinQuantity(): ?float
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(?float $minQuantity): self
    {
        $this->minQuantity = $minQuantity;

        return $this;
    }

    public function getMaxQuantity(): ?float
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(?float $maxQuantity): self
    {
        $this->maxQuantity = $maxQuantity;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

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
        return new ProductBundleItemTableMap();
    }
}

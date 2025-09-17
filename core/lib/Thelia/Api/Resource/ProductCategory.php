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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\ProductCategoryTableMap;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/product_categories/{product}/categories/{category}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/product_categories/{product}/categories/{category}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[CompositeIdentifiers(['category', 'product'])]
class ProductCategory implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_categories:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product_categories:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product_categories:write';
    public const GROUP_FRONT_READ = 'front:product_categories:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product_categories:read:single';

    #[Relation(targetResource: Category::class)]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ,
        Product::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_WRITE,
    ])]
    public Category $category;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public Product $product;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_FRONT_READ,
        Product::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_ADMIN_WRITE,
    ])]
    public ?bool $defaultCategory = false;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_FRONT_READ,
        Product::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_ADMIN_WRITE,
    ])]
    public int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        $this->category = $category;

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

    public function getDefaultCategory(): ?bool
    {
        return $this->defaultCategory;
    }

    public function setDefaultCategory(?bool $defaultCategory): self
    {
        $this->defaultCategory = $defaultCategory;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
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
        return new ProductCategoryTableMap();
    }
}

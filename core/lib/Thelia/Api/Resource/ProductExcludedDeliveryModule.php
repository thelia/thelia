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
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotNull;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\ProductExcludedDeliveryModuleProcessor;
use Thelia\Model\Map\ProductExcludedDeliveryModuleTableMap;

/**
 * A carrier a product cannot travel with.
 *
 * Writes go through ProductExcludedDeliveryModuleProcessor, which calls the product
 * facade: the carrier is checked to be an active delivery module and the events fire.
 * A catalogue import reads the exclusions of a product by filtering on `product.id`.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_excluded_delivery_modules',
            processor: ProductExcludedDeliveryModuleProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_excluded_delivery_modules',
        ),
        new Get(
            uriTemplate: '/admin/product_excluded_delivery_modules/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/product_excluded_delivery_modules/{id}',
            processor: ProductExcludedDeliveryModuleProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'product.id',
        'module.id',
    ],
)]
class ProductExcludedDeliveryModule implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_excluded_delivery_module:read';
    public const GROUP_ADMIN_WRITE = 'admin:product_excluded_delivery_module:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public Product $product;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public Module $module;

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

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): self
    {
        $this->module = $module;

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
        return new ProductExcludedDeliveryModuleTableMap();
    }
}

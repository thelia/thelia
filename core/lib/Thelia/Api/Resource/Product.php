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

use ApiPlatform\Metadata\ApiFilter;
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
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/products'
        ),
        new GetCollection(
            uriTemplate: '/admin/products'
        ),
        new Get(
            uriTemplate: '/admin/products/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/products/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/products/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'ref',
        'productCategories.category.id',
        'title' => 'word_start',
    ]
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'virtual',
        'productCategories.defaultCategory',
    ]
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'ref',
        'productCategories.position',
    ]
)]
class Product extends AbstractTranslatableResource
{
    public const GROUP_READ = 'product:read';
    public const GROUP_READ_SINGLE = 'product:read:single';
    public const GROUP_WRITE = 'product:write';

    #[Groups([self::GROUP_READ, ProductCategory::GROUP_READ, OrderProduct::GROUP_READ, ProductAssociatedContent::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: TaxRule::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public TaxRule $taxRule;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

    #[Relation(targetResource: Template::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public Template $template;

    #[Relation(targetResource: Brand::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public Brand $brand;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $virtual;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: ProductCategory::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Collection $productCategories;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

    public function __construct()
    {
        $this->productCategories = new ArrayCollection();
        parent::__construct();
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

    public function getTaxRule(): TaxRule
    {
        return $this->taxRule;
    }

    public function setTaxRule(TaxRule $taxRule): self
    {
        $this->taxRule = $taxRule;

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

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

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

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function setVirtual(bool $virtual): self
    {
        $this->virtual = $virtual;

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

    public function getProductCategories(): Collection
    {
        return $this->productCategories;
    }

    public function setProductCategories(Collection $productCategories): self
    {
        $this->productCategories = $productCategories;

        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Product::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ProductI18n::class;
    }
}

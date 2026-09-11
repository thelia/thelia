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
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\ProductAssociationTypeProcessor;
use Thelia\Model\Map\ProductAssociationTypeTableMap;

/**
 * The types a relation between two products can carry, and the blocks a product
 * sheet offers because of them.
 *
 * Every write goes through ProductAssociationTypeProcessor rather than the Propel
 * persist processor: the guards a merchant meets in the back office are the ones
 * the core holds — the accessory type may not be deleted, a type still carrying
 * relations may not either, and a code never changes once set. Persisting straight
 * to Propel here would let the API walk past all three.
 *
 * The `code` is what the core, the front-office blocks and the migration address a
 * type by. It is writable on creation and ignored afterwards; the translatable
 * title is what a merchant rewords instead.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/product_association_types',
            processor: ProductAssociationTypeProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/product_association_types',
        ),
        new Get(
            uriTemplate: '/admin/product_association_types/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/product_association_types/{id}',
            processor: ProductAssociationTypeProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/product_association_types/{id}',
            processor: ProductAssociationTypeProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/product_association_types/{id}',
            processor: ProductAssociationTypeProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/product_association_types',
        ),
        new Get(
            uriTemplate: '/front/product_association_types/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
        'reciprocal',
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'code',
    ],
)]
class ProductAssociationType extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:product_association_type:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:product_association_type:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:product_association_type:write';
    public const GROUP_FRONT_READ = 'front:product_association_type:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:product_association_type:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        ProductAssociation::GROUP_ADMIN_READ,
        ProductAssociation::GROUP_FRONT_READ,
        ProductAssociation::GROUP_ADMIN_WRITE,
    ])]
    public ?int $id = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        self::GROUP_ADMIN_WRITE,
        ProductAssociation::GROUP_ADMIN_READ,
        ProductAssociation::GROUP_FRONT_READ,
    ])]
    public string $code;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $reciprocal;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        self::GROUP_ADMIN_WRITE,
        ProductAssociation::GROUP_ADMIN_READ,
        ProductAssociation::GROUP_FRONT_READ,
    ])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

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

    public function isReciprocal(): bool
    {
        return $this->reciprocal;
    }

    public function setReciprocal(bool $reciprocal): self
    {
        $this->reciprocal = $reciprocal;

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
        return new ProductAssociationTypeTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ProductAssociationTypeI18n::class;
    }
}

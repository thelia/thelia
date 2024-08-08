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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\AttributeAvTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/attribute_avs'
        ),
        new GetCollection(
            uriTemplate: '/admin/attribute_avs'
        ),
        new Get(
            uriTemplate: '/admin/attribute_avs/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/attribute_avs/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/attribute_avs/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/attribute_avs'
        ),
        new Get(
            uriTemplate: '/front/attribute_avs/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'title' => 'exact',
    ]
)]
class AttributeAv extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:attribute_av:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:attribute_av:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:attribute_av:write';

    public const GROUP_FRONT_READ = 'front:attribute_av:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:attribute_av:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
        ProductSaleElements::GROUP_ADMIN_READ_SINGLE,
        AttributeCombination::GROUP_ADMIN_WRITE,
    ])]
    public ?int $id = null;

    #[Relation(targetResource: Attribute::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public Attribute $attribute;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        self::GROUP_ADMIN_WRITE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
        ProductSaleElements::GROUP_ADMIN_READ_SINGLE,
        ProductSaleElements::GROUP_FRONT_READ_SINGLE,
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

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(Attribute $attribute): self
    {
        $this->attribute = $attribute;

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
        return new AttributeAvTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return AttributeAvI18n::class;
    }
}

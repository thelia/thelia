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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\AccessoryTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/accessory'
        ),
        new GetCollection(
            uriTemplate: '/admin/accessory'
        ),
        new Get(
            uriTemplate: '/admin/accessory/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/accessory/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]]
        ),
        new Patch(
            uriTemplate: '/admin/accessory/{id}',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE, self::GROUP_ADMIN_WRITE_UPDATE]]
        ),
        new Delete(
            uriTemplate: '/admin/accessory/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'product.id',
        'accessory.id',
    ]
)]
class Accessory implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:accessory:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:accessory:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:accessory:write';
    public const GROUP_ADMIN_WRITE_UPDATE = 'admin:accessory:write:update';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class, relationAlias: 'ProductRelatedByProductId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public Product $product;

    #[Relation(targetResource: Product::class, relationAlias: 'ProductRelatedByAccessory')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public Product $accessory;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position;

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

    public function getAccessory(): Product
    {
        return $this->accessory;
    }

    public function setAccessory(Product $accessory): self
    {
        $this->accessory = $accessory;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new AccessoryTableMap();
    }
}

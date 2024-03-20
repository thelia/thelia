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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\BrandTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/brands'
        ),
        new GetCollection(
            uriTemplate: '/admin/brands'
        ),
        new Get(
            uriTemplate: '/admin/brands/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/brands/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/brands/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/brands'
        ),
        new Get(
            uriTemplate: '/front/brands/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
class Brand extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:brand:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:brand:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:brand:write';

    public const GROUP_FRONT_READ = 'front:brand:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:brand:read:single';

    #[Groups([self::GROUP_ADMIN_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_ADMIN_READ,
        Product::GROUP_ADMIN_WRITE,
        BrandImage::GROUP_ADMIN_READ_SINGLE,
        BrandDocument::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public bool $visible;

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ,
        Product::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    public ?int $position = null;

    #[ApiProperty(
        types: 'object'
    )]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        self::GROUP_ADMIN_WRITE,
    ])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new BrandTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return BrandI18n::class;
    }
}

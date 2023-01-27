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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/categories'
        ),
        new GetCollection(
            uriTemplate: '/admin/categories'
        ),
        new Get(
            uriTemplate: '/admin/categories/{id}'
        ),
        new Put(
            uriTemplate: '/admin/categories/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/categories/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class Category extends AbstractTranslatableResource
{
    public const GROUP_READ = 'category:read';
    public const GROUP_READ_SINGLE = 'category:read:single';
    public const GROUP_WRITE = 'category:write';

    #[Groups([self::GROUP_READ, Product::GROUP_READ, ProductCategory::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    public string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Category::class;
    }

    public static function getTranslatableFields(): array
    {
        return [
            'title',
            'chapo',
        ];
    }

    public static function getI18nResourceClass(): string
    {
        return CategoryI18n::class;
    }
}

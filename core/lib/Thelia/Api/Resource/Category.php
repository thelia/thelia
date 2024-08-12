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
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Tools\UrlRewritingTrait;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/categories'
        ),
        new GetCollection(
            uriTemplate: '/admin/categories'
        ),
        new Get(
            uriTemplate: '/admin/categories/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/categories/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/categories/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/categories'
        ),
        new Get(
            uriTemplate: '/front/categories/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id'
    ]
)]
class Category extends AbstractTranslatableResource
{
    use UrlRewritingTrait;
    public const GROUP_ADMIN_READ = 'admin:category:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:category:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:category:write';

    public const GROUP_FRONT_READ = 'front:category:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:category:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
        Product::GROUP_ADMIN_WRITE,
        ProductCategory::GROUP_ADMIN_READ,
        CategoryImage::GROUP_ADMIN_READ_SINGLE,
        CategoryDocument::GROUP_ADMIN_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public int $parent;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?int $position;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?int $defaultTemplateId;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, Product::GROUP_FRONT_READ_SINGLE])]
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

    public function getParent(): int
    {
        return $this->parent;
    }

    public function setParent(int $parent): self
    {
        $this->parent = $parent;

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

    public function getDefaultTemplateId(): ?int
    {
        return $this->defaultTemplateId;
    }

    public function setDefaultTemplateId(?int $defaultTemplateId): self
    {
        $this->defaultTemplateId = $defaultTemplateId;

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
        return new CategoryTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return CategoryI18n::class;
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getPublicUrl()
    {
        /** @var \Thelia\Model\Category $propelModel */
        $propelModel = $this->getPropelModel();
        return $this->getUrl($propelModel->getLocale());
    }

    public function getRewrittenUrlViewName(): string
    {
        /** @var \Thelia\Model\Category $propelModel */
        $propelModel = $this->getPropelModel();
        return $propelModel->getRewrittenUrlViewName();
    }
}

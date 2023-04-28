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
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/exports'
        ),
        new GetCollection(
            uriTemplate: '/admin/exports'
        ),
        new Get(
            uriTemplate: '/admin/exports/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/exports/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/exports/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Export extends AbstractTranslatableResource
{
    public const GROUP_READ = 'export:read';
    public const GROUP_READ_SINGLE = 'export:read:single';
    public const GROUP_WRITE = 'export:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position = null;

    #[Relation(targetResource: ExportCategory::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ExportCategory $exportCategory;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $handleClass;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
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

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;

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

    public function getExportCategory(): ExportCategory
    {
        return $this->exportCategory;
    }

    public function setExportCategory(ExportCategory $exportCategory): self
    {
        $this->exportCategory = $exportCategory;

        return $this;
    }

    public function getHandleClass(): string
    {
        return $this->handleClass;
    }

    public function setHandleClass(string $handleClass): self
    {
        $this->handleClass = $handleClass;

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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Export::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ExportI18n::class;
    }
}

<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/folders'
        ),
        new GetCollection(
            uriTemplate: '/admin/folders'
        ),
        new Get(
            uriTemplate: '/admin/folders/{id}'
        ),
        new Put(
            uriTemplate: '/admin/folders/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/folders/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Folder extends AbstractTranslatableResource
{
    public const GROUP_READ = 'folder:read';
    public const GROUP_READ_SINGLE = 'folder:read:single';
    public const GROUP_WRITE = 'folder:write';

    #[Groups([self::GROUP_READ, ContentFolder::GROUP_READ_SINGLE, FolderImage::GROUP_READ_SINGLE, FolderDocument::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $parent;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?DateTime $updatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public int $version;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?DateTime $versionCreatedAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $versionCreatedBy;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Folder
    {
        $this->id = $id;
        return $this;
    }

    public function isParent(): bool
    {
        return $this->parent;
    }

    public function setParent(bool $parent): Folder
    {
        $this->parent = $parent;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): Folder
    {
        $this->visible = $visible;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Folder
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Folder
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Folder
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): Folder
    {
        $this->version = $version;
        return $this;
    }

    public function getVersionCreatedAt(): ?DateTime
    {
        return $this->versionCreatedAt;
    }

    public function setVersionCreatedAt(?DateTime $versionCreatedAt): Folder
    {
        $this->versionCreatedAt = $versionCreatedAt;
        return $this;
    }

    public function getVersionCreatedBy(): ?string
    {
        return $this->versionCreatedBy;
    }

    public function setVersionCreatedBy(?string $versionCreatedBy): Folder
    {
        $this->versionCreatedBy = $versionCreatedBy;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Folder::class;
    }

    public static function getI18nResourceClass(): string
    {
        return FolderI18n::class;
    }
}

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
            uriTemplate: '/admin/contents'
        ),
        new GetCollection(
            uriTemplate: '/admin/contents'
        ),
        new Get(
            uriTemplate: '/admin/contents/{id}'
        ),
        new Put(
            uriTemplate: '/admin/contents/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/contents/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Content extends AbstractTranslatableResource
{
    public const GROUP_READ = 'content:read';
    public const GROUP_READ_SINGLE = 'content:read:single';
    public const GROUP_WRITE = 'content:write';

    #[Groups([self::GROUP_READ, ContentFolder::GROUP_READ_SINGLE, ContentImage::GROUP_READ_SINGLE, ContentDocument::GROUP_READ_SINGLE])]
    public ?int $id = null;

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

    public function setId(?int $id): Content
    {
        $this->id = $id;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): Content
    {
        $this->visible = $visible;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Content
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Content
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Content
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): Content
    {
        $this->version = $version;
        return $this;
    }

    public function getVersionCreatedAt(): ?DateTime
    {
        return $this->versionCreatedAt;
    }

    public function setVersionCreatedAt(?DateTime $versionCreatedAt): Content
    {
        $this->versionCreatedAt = $versionCreatedAt;
        return $this;
    }

    public function getVersionCreatedBy(): ?string
    {
        return $this->versionCreatedBy;
    }

    public function setVersionCreatedBy(?string $versionCreatedBy): Content
    {
        $this->versionCreatedBy = $versionCreatedBy;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Content::class;
    }

    public static function getI18nResourceClass(): string
    {
        return ContentI18n::class;
    }
}

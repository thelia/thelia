<?php

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
            uriTemplate: '/admin/content_folders'
        ),
        new GetCollection(
            uriTemplate: '/admin/content_folders'
        ),
        new Get(
            uriTemplate: '/admin/content_folders/{id}'
        ),
        new Put(
            uriTemplate: '/admin/content_folders/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/content_folders/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
class ContentFolder extends AbstractPropelResource
{
    public const GROUP_READ = 'content_folder:read';
    public const GROUP_READ_SINGLE = 'content_folder:read:single';
    public const GROUP_WRITE = 'content_folder:write';

    #[Relation(targetResource: Folder::class)]
    #[Groups([self::GROUP_READ])]
    public Content $content;

    #[Relation(targetResource: Folder::class)]
    #[Groups([self::GROUP_READ])]
    public Folder $folder;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public bool $defaultFolder;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?int $position;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $updatedAt;

    public function getContent(): Content
    {
        return $this->content;
    }

    public function setContent(Content $content): ContentFolder
    {
        $this->content = $content;
        return $this;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function setFolder(Folder $folder): ContentFolder
    {
        $this->folder = $folder;
        return $this;
    }

    public function isDefaultFolder(): bool
    {
        return $this->defaultFolder;
    }

    public function setDefaultFolder(bool $defaultFolder): ContentFolder
    {
        $this->defaultFolder = $defaultFolder;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): ContentFolder
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): ContentFolder
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): ContentFolder
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\ContentFolder::class;
    }
}

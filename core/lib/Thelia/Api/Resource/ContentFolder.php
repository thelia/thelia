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
use ApiPlatform\Metadata\Get;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\ContentFolderTableMap;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/content_folders/{content}/folders/{folder}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
#[CompositeIdentifiers(['content', 'folder'])]
class ContentFolder implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'content_folder:read';
    public const GROUP_READ_SINGLE = 'content_folder:read:single';
    public const GROUP_WRITE = 'content_folder:write';

    #[Relation(targetResource: Content::class)]
    #[Groups([self::GROUP_READ])]
    public Content $content;

    #[Relation(targetResource: Folder::class)]
    #[Groups([self::GROUP_READ])]
    public Folder $folder;

    #[Groups([self::GROUP_READ])]
    public bool $defaultFolder = false;

    #[Groups([self::GROUP_READ])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getContent(): Content
    {
        return $this->content;
    }

    public function setContent(Content $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function setFolder(Folder $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function isDefaultFolder(): bool
    {
        return $this->defaultFolder;
    }

    public function setDefaultFolder(bool $defaultFolder): self
    {
        $this->defaultFolder = $defaultFolder;

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
        return new ContentFolderTableMap();
    }
}

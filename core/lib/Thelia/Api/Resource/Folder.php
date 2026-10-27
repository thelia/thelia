<?php

declare(strict_types=1);

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
use Symfony\Component\Clock\Clock;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Domain\CMS\Publication\Exception\PublicationException;
use Thelia\Domain\CMS\Publication\PublicationStatus;
use Thelia\Domain\CMS\Publication\PublishableInterface;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Tools\UrlRewritingTrait;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/folders',
            exceptionToStatus: [PublicationException::class => 422],
        ),
        new GetCollection(
            uriTemplate: '/admin/folders',
        ),
        new Get(
            uriTemplate: '/admin/folders/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/folders/{id}',
            exceptionToStatus: [PublicationException::class => 422],
        ),
        new Patch(
            uriTemplate: '/admin/folders/{id}',
            exceptionToStatus: [PublicationException::class => 422],
        ),
        new Delete(
            uriTemplate: '/admin/folders/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/folders',
        ),
        new Get(
            uriTemplate: '/front/folders/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'parent',
        'contentFolders.content.id',
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'id',
        'parent',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'visible',
    ],
)]
class Folder extends AbstractTranslatableResource
{
    use UrlRewritingTrait;

    public const GROUP_ADMIN_READ = 'admin:folder:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:folder:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:folder:write';
    public const GROUP_FRONT_READ = 'front:folder:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:folder:read:single';

    #[Groups([self::GROUP_ADMIN_READ,
        ContentFolder::GROUP_ADMIN_READ,
        FolderImage::GROUP_ADMIN_READ_SINGLE,
        FolderDocument::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_FRONT_READ,
        Content::GROUP_FRONT_READ,
        Content::GROUP_ADMIN_READ_SINGLE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public int $parent;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public bool $visible;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?int $position = null;

    /**
     * The dates are the back office's: a front reads published rows only, and when a row
     * goes offline, or went online, is nothing the shop tells its visitors.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?\DateTimeInterface $publishAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[Assert\GreaterThan(propertyPath: 'publishAt', message: 'The withdrawal date must come after the publication date.')]
    public ?\DateTimeInterface $unpublishAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    #[Relation(targetResource: ContentFolder::class, excludedGroups: [Content::GROUP_ADMIN_READ, Content::GROUP_FRONT_READ, Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ])]
    public array $contentFolders = [];

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
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

    public function isParent(): bool
    {
        return 0 !== $this->parent;
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

    public function getPublishAt(): ?\DateTimeInterface
    {
        return $this->publishAt;
    }

    public function setPublishAt(?\DateTimeInterface $publishAt): self
    {
        $this->publishAt = $publishAt;

        return $this;
    }

    public function getUnpublishAt(): ?\DateTimeInterface
    {
        return $this->unpublishAt;
    }

    public function setUnpublishAt(?\DateTimeInterface $unpublishAt): self
    {
        $this->unpublishAt = $unpublishAt;

        return $this;
    }

    /**
     * The publication state read from the row: draft, scheduled, published or unpublished.
     * Read only; the state is changed by writing `visible`, `publishAt` and `unpublishAt`.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getPublicationState(): ?string
    {
        $propelModel = $this->getPropelModel();

        if (!$propelModel instanceof PublishableInterface) {
            return null;
        }

        return PublicationStatus::ofPublishable($propelModel, Clock::get()->now())->state->value;
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

    public function getContentFolders(): array
    {
        return $this->contentFolders;
    }

    public function setContentFolders(array $contentFolders): self
    {
        $this->contentFolders = $contentFolders;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new FolderTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return FolderI18n::class;
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getPublicUrl()
    {
        /** @var \Thelia\Model\Category $propelModel */
        $propelModel = $this->getPropelModel();

        if (!$locale = $propelModel?->getLocale()) {
            $locale = $this->getDefaultLocale();
        }

        return $this->getUrl($locale);
    }

    public function getRewrittenUrlViewName(): string
    {
        /** @var \Thelia\Model\Category $propelModel */
        $propelModel = $this->getPropelModel();

        return $propelModel?->getRewrittenUrlViewName() ?: '';
    }
}

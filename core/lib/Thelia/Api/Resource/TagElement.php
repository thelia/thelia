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
use ApiPlatform\Metadata\Post;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\TagElementTableMap;

/**
 * Attachment of a tag to a shop object, so an external tool can mark a customer.
 *
 * No Put or Patch: re-pointing an attachment at another object or another tag is
 * a delete and a create, and updating in place would walk into the unique
 * triplet whenever the target already carries the tag.
 *
 * Administration only, like Tag itself.
 *
 * Filtering is declared on the scalar columns only. A `tag.id` filter was
 * measured to be accepted and then silently ignored on this resource — the
 * collection came back whole — even though the same dotted form does filter on
 * Address (`customer.id`). Shipping a filter that returns everything is worse
 * than shipping none, so it is left out until the Propel filter bridge is
 * looked at. The back-office reads the attachments through Propel, not through
 * this resource, so nothing in the delivered feature depends on it.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/tag-elements'),
        new GetCollection(uriTemplate: '/admin/tag-elements'),
        new Get(
            uriTemplate: '/admin/tag-elements/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Delete(uriTemplate: '/admin/tag-elements/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'elementKey',
        'elementId',
    ],
)]
class TagElement implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:tag-element:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:tag-element:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:tag-element:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Tag::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public Tag $tag;

    /**
     * Which kind of object this attachment points at, for instance `customer`.
     * A plain string rather than an enum: a module tagging its own objects
     * declares its own key, and core cannot enumerate those.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank]
    #[Length(max: 100)]
    public string $elementKey;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[Positive]
    public int $elementId;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }

    public function setTag(Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function getElementKey(): string
    {
        return $this->elementKey;
    }

    public function setElementKey(string $elementKey): self
    {
        $this->elementKey = $elementKey;

        return $this;
    }

    public function getElementId(): int
    {
        return $this->elementId;
    }

    public function setElementId(int $elementId): self
    {
        $this->elementId = $elementId;

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
        return new TagElementTableMap();
    }
}

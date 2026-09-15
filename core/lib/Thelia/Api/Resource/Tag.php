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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\TagTableMap;

/**
 * A free-text marker an administrator puts on a shop object.
 *
 * Administration only: tags are internal, so this resource declares no /front
 * operation. Its reach is guarded by AdminApiPermissionListener through the
 * AdminResources code mapped in AdminApiResourcePermissions.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/tags'),
        new GetCollection(uriTemplate: '/admin/tags'),
        new Get(
            uriTemplate: '/admin/tags/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(uriTemplate: '/admin/tags/{id}'),
        new Patch(uriTemplate: '/admin/tags/{id}'),
        new Delete(uriTemplate: '/admin/tags/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'label' => 'partial',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'id',
        'label',
    ],
)]
class Tag implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:tag:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:tag:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:tag:write';

    #[Groups([self::GROUP_ADMIN_READ, TagElement::GROUP_ADMIN_READ])]
    public ?int $id = null;

    /**
     * Free text, so bounded and escaped at rendering. Uniqueness is the unique
     * index on the column, which under utf8mb4_general_ci also folds case and
     * Latin accents: "vip" resolves to an existing "VIP" rather than to a
     * second tag.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, TagElement::GROUP_ADMIN_READ])]
    // The trim normalizer is what makes NotBlank see a label of spaces as blank:
    // without it "   " passes validation, Tag::preSave() then reduces it to an empty
    // string, and the API creates a tag with no label at all. TagService refuses that
    // case, but the API never goes through the service — it saves the model directly.
    #[NotBlank(normalizer: 'trim')]
    #[Length(max: 100)]
    public string $label;

    /**
     * Hexadecimal colour, validated against a strict format rather than trusted:
     * this value reaches a style attribute in the back-office.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, TagElement::GROUP_ADMIN_READ])]
    #[Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'The colour must be a hexadecimal code, for instance #1A2B3C.')]
    public ?string $colorCode = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getColorCode(): ?string
    {
        return $this->colorCode;
    }

    public function setColorCode(?string $colorCode): self
    {
        $this->colorCode = $colorCode;

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
        return new TagTableMap();
    }
}

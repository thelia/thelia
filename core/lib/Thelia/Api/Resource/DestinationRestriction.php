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
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\DestinationRestrictionProcessor;
use Thelia\Model\Map\DestinationRestrictionTableMap;

/**
 * A group of products that cannot ship to some destinations (mode 0), or only to some
 * (mode 1). Admin only: the front never lists the groups of a shop.
 *
 * Writes go through DestinationRestrictionProcessor, which calls the destination
 * restriction facade, the one the back office and the import use.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/destination_restrictions', processor: DestinationRestrictionProcessor::class),
        new GetCollection(uriTemplate: '/admin/destination_restrictions'),
        new Get(uriTemplate: '/admin/destination_restrictions/{id}'),
        new Put(uriTemplate: '/admin/destination_restrictions/{id}', processor: DestinationRestrictionProcessor::class),
        new Patch(uriTemplate: '/admin/destination_restrictions/{id}', processor: DestinationRestrictionProcessor::class),
        new Delete(uriTemplate: '/admin/destination_restrictions/{id}', processor: DestinationRestrictionProcessor::class),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['position'])]
#[ApiFilter(filterClass: BooleanFilter::class, properties: ['active'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['id', 'code'])]
class DestinationRestriction extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:destination_restriction:read';
    public const GROUP_ADMIN_WRITE = 'admin:destination_restriction:write';

    #[Groups([self::GROUP_ADMIN_READ, DestinationRestrictionCountry::GROUP_ADMIN_READ, DestinationRestrictionCountry::GROUP_ADMIN_WRITE, ProductDestinationRestriction::GROUP_ADMIN_READ, ProductDestinationRestriction::GROUP_ADMIN_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank]
    public ?string $code = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[Choice(choices: [0, 1])]
    public int $mode = 0;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $active = true;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

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
        return new DestinationRestrictionTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return DestinationRestrictionI18n::class;
    }
}

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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\TaxTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/taxes'
        ),
        new GetCollection(
            uriTemplate: '/admin/taxes'
        ),
        new Get(
            uriTemplate: '/admin/taxes/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/taxes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/taxes/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Tax extends AbstractTranslatableResource
{
    public const GROUP_READ = 'tax:read';
    public const GROUP_READ_SINGLE = 'tax:read:single';
    public const GROUP_WRITE = 'tax:write';

    #[Groups([self::GROUP_READ, TaxRuleCountry::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $type;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $serializedRequirements;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSerializedRequirements(): string
    {
        return $this->serializedRequirements;
    }

    public function setSerializedRequirements(string $serializedRequirements): self
    {
        $this->serializedRequirements = $serializedRequirements;

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
        return new TaxTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return TaxI18n::class;
    }
}

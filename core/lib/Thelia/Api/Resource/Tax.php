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
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
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
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/taxes/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/taxes/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/taxes/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/taxes'
        ),
        new Get(
            uriTemplate: '/front/taxes/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'product.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'product_image.product_id',
        ],
    ]
)]
class Tax extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:tax:read';

    public const GROUP_ADMIN_READ_SINGLE = 'admin:tax:read:single';

    public const GROUP_ADMIN_WRITE = 'admin:tax:write';

    public const GROUP_FRONT_READ = 'front:tax:read';

    public const GROUP_FRONT_READ_SINGLE = 'front:tax:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        TaxRuleCountry::GROUP_ADMIN_READ,
        TaxRuleCountry::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public string $type;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public string $serializedRequirements;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
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

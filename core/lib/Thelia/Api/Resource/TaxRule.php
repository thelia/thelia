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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Model\Map\TaxRuleTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/tax_rules'
        ),
        new GetCollection(
            uriTemplate: '/admin/tax_rules'
        ),
        new Get(
            uriTemplate: '/admin/tax_rules/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/tax_rules/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/tax_rules/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/tax_rules/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/tax_rules'
        ),
        new Get(
            uriTemplate: '/front/tax_rules/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]]
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
class TaxRule extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:tax_rule:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:tax_rule:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:tax_rule:write';

    public const GROUP_FRONT_READ = 'front:tax_rule:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:tax_rule:read:single';

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        TaxRuleCountry::GROUP_ADMIN_READ,
        TaxRuleCountry::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ,
        Product::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_WRITE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ, self::GROUP_ADMIN_WRITE])]
    public ?bool $isDefault = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $updatedAt;

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

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;

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
        return new TaxRuleTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return TaxRuleI18n::class;
    }
}

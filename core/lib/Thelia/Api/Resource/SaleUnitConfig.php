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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\SaleUnitConfigTableMap;
use Thelia\Model\SaleUnitConfigQuery;

/**
 * A store setting a sale unit sets for itself: its name, its sender, the identity
 * printed on its invoices. A setting the unit does not set keeps the national value.
 * Only the names of ConfigQuery::SALE_UNIT_SCOPED_NAMES are accepted.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/sale_unit_configs',
        ),
        new GetCollection(
            uriTemplate: '/admin/sale_unit_configs',
        ),
        new Get(
            uriTemplate: '/admin/sale_unit_configs/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/sale_unit_configs/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/sale_unit_configs/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/sale_unit_configs/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'name',
        'saleUnit.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_config.sale_unit_id',
        ],
    ],
)]
class SaleUnitConfig implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:sale_unit_config:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:sale_unit_config:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:sale_unit_config:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: SaleUnit::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public SaleUnit $saleUnit;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    #[Choice(choices: ConfigQuery::SALE_UNIT_SCOPED_NAMES, groups: [self::GROUP_ADMIN_WRITE])]
    public string $name;

    /**
     * An empty string is a value: it blanks the setting for the unit. Removing the
     * row is what brings the national value back.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public string $value;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * One value per unit and setting: a second one is refused here, before the unique
     * key of the table refuses it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->saleUnit, $this->name)) {
            return;
        }

        $query = SaleUnitConfigQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByName($this->name);

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This sale unit already sets this store setting.')
                ->atPath('name')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSaleUnit(): SaleUnit
    {
        return $this->saleUnit;
    }

    public function setSaleUnit(SaleUnit $saleUnit): self
    {
        $this->saleUnit = $saleUnit;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

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
        return new SaleUnitConfigTableMap();
    }
}

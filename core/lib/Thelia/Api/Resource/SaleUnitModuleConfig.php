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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\SaleUnitModuleConfigTableMap;
use Thelia\Model\SaleUnitModuleConfigQuery;

/**
 * A value a sale unit gives a module setting, read by the module instead of the
 * national one: the account of a payment provider, the sender of a carrier.
 *
 * A secret value (a key, a password) is written and never read back: the API
 * answers null for it, a write that sends no value keeps it, and turning a secret
 * back into a readable value takes a new value, so the flag cannot be used to
 * reveal the stored one. Deleting the row is what removes it.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/sale_unit_module_configs',
        ),
        new GetCollection(
            uriTemplate: '/admin/sale_unit_module_configs',
        ),
        new Get(
            uriTemplate: '/admin/sale_unit_module_configs/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/sale_unit_module_configs/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/sale_unit_module_configs/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/sale_unit_module_configs/{id}',
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
            'fieldPath' => 'sale_unit_module_config.sale_unit_id',
        ],
        'module.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_module_config.module_id',
        ],
    ],
)]
class SaleUnitModuleConfig implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:sale_unit_module_config:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:sale_unit_module_config:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:sale_unit_module_config:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: SaleUnit::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public SaleUnit $saleUnit;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public Module $module;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Length(max: 255, groups: [self::GROUP_ADMIN_WRITE])]
    public string $name;

    /**
     * Null for a secret, which the model never hands to the API; a write that sends no
     * value for a secret leaves the stored one alone instead of blanking it.
     */
    #[Column(propelFieldName: 'valueForApi')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $value = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $secret = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * One value per unit, module and setting: a second one is refused here, before
     * the unique key of the table refuses it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->saleUnit, $this->module, $this->name)) {
            return;
        }

        $query = SaleUnitModuleConfigQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByModuleId($this->module->getId())
            ->filterByName($this->name);

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This sale unit already sets this module setting.')
                ->atPath('name')
                ->addViolation();
        }
    }

    /**
     * A secret is replaced, never revealed: making it readable again takes a new value.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateSecretIsNotRevealed(ExecutionContextInterface $context): void
    {
        if (null === $this->id || $this->secret || null !== $this->value) {
            return;
        }

        $stored = SaleUnitModuleConfigQuery::create()->findPk($this->id);

        if (null !== $stored && true === $stored->getSecret()) {
            $context->buildViolation('A secret value cannot be made readable without being replaced: send a new value.')
                ->atPath('secret')
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

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): self
    {
        $this->module = $module;

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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function setSecret(bool $secret): self
    {
        $this->secret = $secret;

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
        return new SaleUnitModuleConfigTableMap();
    }
}

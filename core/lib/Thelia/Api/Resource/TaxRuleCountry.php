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
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\TaxRuleTableMap;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/tax_rules_country/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class TaxRuleCountry implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'tax_rule_country:read';
    public const GROUP_READ_SINGLE = 'tax_rule_country:read:single';
    public const GROUP_WRITE = 'tax_rule_country:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Relation(targetResource: TaxRule::class)]
    #[Groups([self::GROUP_READ])]
    public ?TaxRule $taxRule;

    #[Relation(targetResource: Country::class)]
    #[Groups([self::GROUP_READ])]
    public ?Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups([self::GROUP_READ])]
    public ?State $state;

    #[Relation(targetResource: Tax::class)]
    #[Groups([self::GROUP_READ])]
    public ?Tax $tax;

    #[Groups([self::GROUP_READ])]
    public ?int $position;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTaxRule(): TaxRule
    {
        return $this->taxRule;
    }

    public function setTaxRule(TaxRule $taxRule): self
    {
        $this->taxRule = $taxRule;

        return $this;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function setState(State $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getTax(): Tax
    {
        return $this->tax;
    }

    public function setTax(Tax $tax): self
    {
        $this->tax = $tax;

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
        return new TaxRuleTableMap();
    }
}

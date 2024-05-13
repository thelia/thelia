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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\StateTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/states'
        ),
        new GetCollection(
            uriTemplate: '/admin/states'
        ),
        new Get(
            uriTemplate: '/admin/states/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Get(
            uriTemplate: '/admin/states/iso/{countryIso3}/{stateIso}',
            uriVariables: [
                'countryIso3' => new Link(
                    fromProperty: 'isoalpha3',
                    fromClass: Country::class
                ),
                'stateIso' => new Link(
                    fromProperty: 'isocode',
                    fromClass: State::class
                ),
            ],
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
            name: 'state_by_iso',
        ),
        new Put(
            uriTemplate: '/admin/states/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/states/{id}'
        ),
    ],
    uriVariables: ['id'],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class State extends AbstractTranslatableResource
{
    public const GROUP_ADMIN_READ = 'admin:state:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:state:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:state:write';

    #[Groups([
        self::GROUP_ADMIN_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Address::GROUP_ADMIN_READ,
        TaxRuleCountry::GROUP_ADMIN_READ,
        OrderAddress::GROUP_ADMIN_WRITE,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderAddress::GROUP_ADMIN_READ])]
    public bool $visible;

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, OrderAddress::GROUP_ADMIN_READ])]
    public ?string $isocode;

    #[Relation(targetResource: Country::class)]
    #[Groups(groups: [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Order::GROUP_ADMIN_READ_SINGLE])]
    public Country $country;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt;

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

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getIsocode(): ?string
    {
        return $this->isocode;
    }

    public function setIsocode(?string $isocode): self
    {
        $this->isocode = $isocode;

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
        return new StateTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return StateI18n::class;
    }
}

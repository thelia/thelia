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
use Symfony\Component\Validator\Constraints\NotNull;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\DestinationRestrictionLinkProcessor;
use Thelia\Model\Map\DestinationRestrictionCountryTableMap;

/**
 * A destination a restriction group lists: a whole country, or one region of it.
 *
 * Writes go through DestinationRestrictionLinkProcessor, which checks that the region
 * belongs to the country.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/destination_restriction_countries', processor: DestinationRestrictionLinkProcessor::class),
        new GetCollection(uriTemplate: '/admin/destination_restriction_countries'),
        new Get(uriTemplate: '/admin/destination_restriction_countries/{id}'),
        new Delete(uriTemplate: '/admin/destination_restriction_countries/{id}', processor: DestinationRestrictionLinkProcessor::class),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['destinationRestriction.id', 'country.id'])]
class DestinationRestrictionCountry implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:destination_restriction_country:read';
    public const GROUP_ADMIN_WRITE = 'admin:destination_restriction_country:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: DestinationRestriction::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public DestinationRestriction $destinationRestriction;

    #[Relation(targetResource: Country::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?State $state = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getDestinationRestriction(): DestinationRestriction
    {
        return $this->destinationRestriction;
    }

    public function setDestinationRestriction(DestinationRestriction $destinationRestriction): self
    {
        $this->destinationRestriction = $destinationRestriction;

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

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): self
    {
        $this->state = $state;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new DestinationRestrictionCountryTableMap();
    }
}

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
use Thelia\Model\Map\ProductDestinationRestrictionTableMap;

/**
 * A product in a destination restriction group.
 *
 * Writes go through DestinationRestrictionLinkProcessor, which calls the destination
 * restriction facade: the product form never carries the groups of a product.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/product_destination_restrictions', processor: DestinationRestrictionLinkProcessor::class),
        new GetCollection(uriTemplate: '/admin/product_destination_restrictions'),
        new Get(uriTemplate: '/admin/product_destination_restrictions/{id}'),
        new Delete(uriTemplate: '/admin/product_destination_restrictions/{id}', processor: DestinationRestrictionLinkProcessor::class),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['product.id', 'destinationRestriction.id'])]
class ProductDestinationRestriction implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:product_destination_restriction:read';
    public const GROUP_ADMIN_WRITE = 'admin:product_destination_restriction:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public Product $product;

    #[Relation(targetResource: DestinationRestriction::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public DestinationRestriction $destinationRestriction;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ProductDestinationRestrictionTableMap();
    }
}

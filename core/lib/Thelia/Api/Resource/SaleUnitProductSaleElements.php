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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Security\SaleUnitScopeVoter;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * The stock a sale unit keeps of a sale element. A unit with a row sells from it, and
 * the checkout decrements it, its cancellations and returns give back to it; a unit
 * without a row sells from the shared national counter.
 *
 * Administration only: the front reads the resulting quantity on the sale element.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/sale_unit_product_sale_elements',
            securityPostDenormalize: 'is_granted("'.SaleUnitScopeVoter::SCOPE.'", object)',
        ),
        new GetCollection(
            uriTemplate: '/admin/sale_unit_product_sale_elements',
        ),
        new Get(
            uriTemplate: '/admin/sale_unit_product_sale_elements/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/sale_unit_product_sale_elements/{id}',
            securityPostDenormalize: 'is_granted("'.SaleUnitScopeVoter::SCOPE.'", object)',
        ),
        new Patch(
            uriTemplate: '/admin/sale_unit_product_sale_elements/{id}',
            securityPostDenormalize: 'is_granted("'.SaleUnitScopeVoter::SCOPE.'", object)',
        ),
        new Delete(
            uriTemplate: '/admin/sale_unit_product_sale_elements/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'saleUnit.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_product_sale_elements.sale_unit_id',
        ],
        'productSaleElements.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_product_sale_elements.product_sale_elements_id',
        ],
    ],
)]
class SaleUnitProductSaleElements implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:sale_unit_product_sale_elements:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:sale_unit_product_sale_elements:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:sale_unit_product_sale_elements:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: SaleUnit::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public SaleUnit $saleUnit;

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public ProductSaleElements $productSaleElements;

    /**
     * Not bounded below: a shop that allows a negative stock lets a unit sell past
     * zero the way it lets the national counter.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public float $quantity;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * One stock per unit and sale element: a second one for the same pair is refused
     * here, before the unique key of the table refuses it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->saleUnit, $this->productSaleElements)) {
            return;
        }

        $query = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId());

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This sale unit already keeps a stock of this sale element.')
                ->atPath('productSaleElements')
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

    public function getProductSaleElements(): ProductSaleElements
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(ProductSaleElements $productSaleElements): self
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

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
        return new SaleUnitProductSaleElementsTableMap();
    }
}

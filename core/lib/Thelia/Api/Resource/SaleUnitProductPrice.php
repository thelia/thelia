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
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\SaleUnitProductPriceTableMap;
use Thelia\Model\SaleUnitProductPriceQuery;

/**
 * The price a sale unit sells a sale element at, in one currency, when it does not
 * sell it at the national price: the same columns as a national price, read the same
 * way. A sale element without a row keeps the national price in that unit.
 *
 * Administration only: the front reads the resulting price on the sale element.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/sale_unit_product_prices',
        ),
        new GetCollection(
            uriTemplate: '/admin/sale_unit_product_prices',
        ),
        new Get(
            uriTemplate: '/admin/sale_unit_product_prices/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/sale_unit_product_prices/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/sale_unit_product_prices/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/sale_unit_product_prices/{id}',
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
            'fieldPath' => 'sale_unit_product_price.sale_unit_id',
        ],
        'productSaleElements.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_product_price.product_sale_elements_id',
        ],
        'currency.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'sale_unit_product_price.currency_id',
        ],
    ],
)]
class SaleUnitProductPrice implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:sale_unit_product_price:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:sale_unit_product_price:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:sale_unit_product_price:write';

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

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public Currency $currency;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    #[PositiveOrZero(groups: [self::GROUP_ADMIN_WRITE])]
    public float $price;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[PositiveOrZero(groups: [self::GROUP_ADMIN_WRITE])]
    public float $promoPrice = 0.0;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $fromDefaultCurrency = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * One price per unit, sale element and currency: a second one for the same three
     * is refused here, before the unique key of the table refuses it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->saleUnit, $this->productSaleElements, $this->currency)) {
            return;
        }

        $query = SaleUnitProductPriceQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->filterByCurrencyId($this->currency->getId());

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This sale unit already has a price for this sale element in this currency.')
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

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPromoPrice(): float
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(float $promoPrice): self
    {
        $this->promoPrice = $promoPrice;

        return $this;
    }

    public function getFromDefaultCurrency(): bool
    {
        return $this->fromDefaultCurrency;
    }

    public function setFromDefaultCurrency(bool $fromDefaultCurrency): self
    {
        $this->fromDefaultCurrency = $fromDefaultCurrency;

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
        return new SaleUnitProductPriceTableMap();
    }
}

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
use Thelia\Api\Security\SaleUnitScopeVoter;
use Thelia\Api\State\Processor\CustomerGroupProductPriceProcessor;
use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Model\Map\CustomerGroupProductPriceTableMap;

/**
 * The price a customer group pays for a sale element, in one currency, when it
 * does not pay the catalog price reduced by its discount. A null price or promo
 * price means the group has no price of its own for it: the resolution falls
 * through to the next rule.
 *
 * Administration only: the front reads the resulting price on the sale element. An administrator
 * restricted to a sale unit reads the prices and does not write them: a group price is the same in
 * every unit.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/customer_group_product_prices',
            processor: CustomerGroupProductPriceProcessor::class,
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
        ),
        new GetCollection(
            uriTemplate: '/admin/customer_group_product_prices',
        ),
        new Get(
            uriTemplate: '/admin/customer_group_product_prices/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/customer_group_product_prices/{id}',
            processor: CustomerGroupProductPriceProcessor::class,
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
        ),
        new Patch(
            uriTemplate: '/admin/customer_group_product_prices/{id}',
            processor: CustomerGroupProductPriceProcessor::class,
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
        ),
        new Delete(
            uriTemplate: '/admin/customer_group_product_prices/{id}',
            security: 'is_granted("'.SaleUnitScopeVoter::UNRESTRICTED.'")',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'customerGroup.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'customer_group_product_price.customer_group_id',
        ],
        'productSaleElements.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'customer_group_product_price.product_sale_elements_id',
        ],
        'currency.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'customer_group_product_price.currency_id',
        ],
    ],
)]
class CustomerGroupProductPrice implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:customer_group_product_price:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:customer_group_product_price:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:customer_group_product_price:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: CustomerGroup::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public CustomerGroup $customerGroup;

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public ProductSaleElements $productSaleElements;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public Currency $currency;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[PositiveOrZero(groups: [self::GROUP_ADMIN_WRITE])]
    public ?float $price = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[PositiveOrZero(groups: [self::GROUP_ADMIN_WRITE])]
    public ?float $promoPrice = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public bool $fromDefaultCurrency = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * A price that is created says one: a row with neither a price nor a promo price would be
     * dropped by the model. An existing one emptied is a deletion, which is allowed (204).
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateAnAmountIsGiven(ExecutionContextInterface $context): void
    {
        if (null === $this->id && null === $this->price && null === $this->promoPrice) {
            $context->buildViolation('A group price needs a price or a promo price.')
                ->atPath('price')
                ->addViolation();
        }
    }

    /**
     * One price per group, sale element and currency: a second one for the same
     * three is refused here, before the unique key of the table refuses it as a
     * server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->customerGroup, $this->productSaleElements, $this->currency)) {
            return;
        }

        $query = CustomerGroupProductPriceQuery::create()
            ->filterByCustomerGroupId($this->customerGroup->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->filterByCurrencyId($this->currency->getId());

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This customer group already has a price for this sale element in this currency.')
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

    public function getCustomerGroup(): CustomerGroup
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroup $customerGroup): self
    {
        $this->customerGroup = $customerGroup;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPromoPrice(): ?float
    {
        return $this->promoPrice;
    }

    public function setPromoPrice(?float $promoPrice): self
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
        return new CustomerGroupProductPriceTableMap();
    }
}

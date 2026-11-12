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
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\PriceListItemTableMap;
use Thelia\Model\PriceListItemQuery;

/**
 * One line of a price list: from a minimum quantity, a sale element costs a price, or the
 * catalog price minus a percentage. Exactly one of the two is set.
 *
 * Administration only: a negotiated price never leaves through the storefront API.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/price_list_items'),
        new GetCollection(uriTemplate: '/admin/price_list_items'),
        new Get(uriTemplate: '/admin/price_list_items/{id}'),
        new Put(uriTemplate: '/admin/price_list_items/{id}'),
        new Patch(uriTemplate: '/admin/price_list_items/{id}'),
        new Delete(uriTemplate: '/admin/price_list_items/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'priceList.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'price_list_item.price_list_id',
        ],
        'productSaleElements.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'price_list_item.product_sale_elements_id',
        ],
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'minQuantity',
        'price',
    ],
)]
class PriceListItem implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:price_list_item:read';
    public const GROUP_ADMIN_WRITE = 'admin:price_list_item:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: PriceList::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public PriceList $priceList;

    #[Relation(targetResource: ProductSaleElements::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public ProductSaleElements $productSaleElements;

    /**
     * The line applies from this quantity on; one when the write leaves it out.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[GreaterThanOrEqual(1, groups: [self::GROUP_ADMIN_WRITE])]
    public ?int $minQuantity = null;

    /**
     * The price without taxes, in the currency of the list.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[PositiveOrZero(groups: [self::GROUP_ADMIN_WRITE])]
    public ?float $price = null;

    /**
     * The discount, as a percentage of the catalog price: more than 0, 100 at most.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[Positive(groups: [self::GROUP_ADMIN_WRITE])]
    #[LessThanOrEqual(100, groups: [self::GROUP_ADMIN_WRITE])]
    public ?float $discountPercent = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    /**
     * A line is a price or a discount, never both and never neither. A write that turns a price
     * into a discount says so by emptying the price.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateOneAmount(ExecutionContextInterface $context): void
    {
        if (null === $this->price && null === $this->discountPercent) {
            $context->buildViolation('A line needs a price or a discount percentage.')
                ->atPath('price')
                ->addViolation();

            return;
        }

        if (null !== $this->price && null !== $this->discountPercent) {
            $context->buildViolation('A line is a price or a discount percentage, not both.')
                ->atPath('discountPercent')
                ->addViolation();
        }
    }

    /**
     * One line per list, sale element and minimum quantity: a second one is refused here, before
     * the unique key of the table refuses it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->priceList, $this->productSaleElements)) {
            return;
        }

        $query = PriceListItemQuery::create()
            ->filterByPriceListId($this->priceList->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->filterByMinQuantity($this->minQuantity ?? 1);

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This price list already has a line for this sale element and this minimum quantity.')
                ->atPath('minQuantity')
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

    public function getPriceList(): PriceList
    {
        return $this->priceList;
    }

    public function setPriceList(PriceList $priceList): self
    {
        $this->priceList = $priceList;

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

    public function getMinQuantity(): ?int
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(?int $minQuantity): self
    {
        $this->minQuantity = $minQuantity;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float|string|null $price): self
    {
        $this->price = null === $price ? null : (float) $price;

        return $this;
    }

    public function getDiscountPercent(): ?float
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(float|string|null $discountPercent): self
    {
        $this->discountPercent = null === $discountPercent ? null : (float) $discountPercent;

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
        return new PriceListItemTableMap();
    }
}

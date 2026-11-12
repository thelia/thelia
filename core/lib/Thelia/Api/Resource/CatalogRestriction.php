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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\CatalogRestrictionTableMap;

/**
 * An assortment rule: a customer group or a customer may, or may not, see a category, a brand or
 * a product. Exactly one scope and exactly one target.
 *
 * Administration only: who may see what is never disclosed to the storefront.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/catalog_restrictions'),
        new GetCollection(uriTemplate: '/admin/catalog_restrictions'),
        new Get(uriTemplate: '/admin/catalog_restrictions/{id}'),
        new Put(uriTemplate: '/admin/catalog_restrictions/{id}'),
        new Patch(uriTemplate: '/admin/catalog_restrictions/{id}'),
        new Delete(uriTemplate: '/admin/catalog_restrictions/{id}'),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'mode' => 'exact',
        'customerGroup.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'catalog_restriction.customer_group_id',
        ],
        'customer.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'catalog_restriction.customer_id',
        ],
    ],
)]
class CatalogRestriction implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:catalog_restriction:read';
    public const GROUP_ADMIN_WRITE = 'admin:catalog_restriction:write';

    public const MODE_ALLOW = 'allow';
    public const MODE_DENY = 'deny';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: CustomerGroup::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?CustomerGroup $customerGroup = null;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?Customer $customer = null;

    #[Relation(targetResource: Category::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?Category $category = null;

    #[Relation(targetResource: Brand::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?Brand $brand = null;

    #[Relation(targetResource: Product::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?Product $product = null;

    /**
     * Deny closes the target and wins over allow; once a customer has one allow rule, whatever no
     * allow rule opens is closed.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Choice(choices: [self::MODE_ALLOW, self::MODE_DENY], groups: [self::GROUP_ADMIN_WRITE])]
    public ?string $mode = null;

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateOneScope(ExecutionContextInterface $context): void
    {
        if (null === $this->customerGroup && null === $this->customer) {
            $context->buildViolation('A rule needs a customer group or a customer.')
                ->atPath('customerGroup')
                ->addViolation();

            return;
        }

        if (null !== $this->customerGroup && null !== $this->customer) {
            $context->buildViolation('A rule applies to a customer group or a customer, not both.')
                ->atPath('customer')
                ->addViolation();
        }
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateOneTarget(ExecutionContextInterface $context): void
    {
        $targets = \count(array_filter([$this->category, $this->brand, $this->product], static fn (?object $target): bool => null !== $target));

        if (0 === $targets) {
            $context->buildViolation('A rule needs a category, a brand or a product.')
                ->atPath('category')
                ->addViolation();
        } elseif ($targets > 1) {
            $context->buildViolation('A rule targets a category, a brand or a product, not several.')
                ->atPath('category')
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

    public function getCustomerGroup(): ?CustomerGroup
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(?CustomerGroup $customerGroup): self
    {
        $this->customerGroup = $customerGroup;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CatalogRestrictionTableMap();
    }
}

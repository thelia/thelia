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
use Thelia\Model\Map\PriceListAssignmentTableMap;
use Thelia\Model\PriceListAssignmentQuery;

/**
 * The customer group or the customer a price list is assigned to: exactly one of the two.
 *
 * Administration only: who is on which list is never disclosed to the storefront.
 */
#[ApiResource(
    operations: [
        new Post(uriTemplate: '/admin/price_list_assignments'),
        new GetCollection(uriTemplate: '/admin/price_list_assignments'),
        new Get(uriTemplate: '/admin/price_list_assignments/{id}'),
        new Put(uriTemplate: '/admin/price_list_assignments/{id}'),
        new Patch(uriTemplate: '/admin/price_list_assignments/{id}'),
        new Delete(uriTemplate: '/admin/price_list_assignments/{id}'),
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
            'fieldPath' => 'price_list_assignment.price_list_id',
        ],
        'customerGroup.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'price_list_assignment.customer_group_id',
        ],
        'customer.id' => [
            'strategy' => 'exact',
            'fieldPath' => 'price_list_assignment.customer_id',
        ],
    ],
)]
class PriceListAssignment implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:price_list_assignment:read';
    public const GROUP_ADMIN_WRITE = 'admin:price_list_assignment:write';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: PriceList::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    #[NotNull(groups: [self::GROUP_ADMIN_WRITE])]
    public PriceList $priceList;

    #[Relation(targetResource: CustomerGroup::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?CustomerGroup $customerGroup = null;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?Customer $customer = null;

    /**
     * A list is assigned to a group or to a customer, never to both and never to nobody.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateOneScope(ExecutionContextInterface $context): void
    {
        if (null === $this->customerGroup && null === $this->customer) {
            $context->buildViolation('An assignment needs a customer group or a customer.')
                ->atPath('customerGroup')
                ->addViolation();

            return;
        }

        if (null !== $this->customerGroup && null !== $this->customer) {
            $context->buildViolation('An assignment targets a customer group or a customer, not both.')
                ->atPath('customer')
                ->addViolation();
        }
    }

    /**
     * A list is assigned once to the same group, and once to the same customer: a second one is
     * refused here, before the unique keys of the table refuse it as a server error.
     */
    #[Callback(groups: [self::GROUP_ADMIN_WRITE])]
    public function validateUniqueness(ExecutionContextInterface $context): void
    {
        if (!isset($this->priceList) || (null === $this->customerGroup) === (null === $this->customer)) {
            return;
        }

        $query = PriceListAssignmentQuery::create()->filterByPriceListId($this->priceList->getId());

        if (null !== $this->customerGroup) {
            $query->filterByCustomerGroupId($this->customerGroup->getId());
        } else {
            $query->filterByCustomerId($this->customer->getId());
        }

        if (null !== $this->id) {
            $query->filterById($this->id, '<>');
        }

        if ($query->exists()) {
            $context->buildViolation('This price list is already assigned to this customer group or customer.')
                ->atPath(null !== $this->customerGroup ? 'customerGroup' : 'customer')
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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new PriceListAssignmentTableMap();
    }
}

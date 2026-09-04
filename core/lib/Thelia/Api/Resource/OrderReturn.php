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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\OrderReturnAdminCreateProcessor;
use Thelia\Api\State\Processor\OrderReturnFrontCreateProcessor;
use Thelia\Api\State\Processor\OrderReturnTransitionProcessor;
use Thelia\Model\Map\OrderReturnTableMap;
use Thelia\Model\OrderReturn as OrderReturnModel;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/order_returns',
        ),
        new Get(
            uriTemplate: '/admin/order_returns/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Post(
            uriTemplate: '/admin/order_returns',
            processor: OrderReturnAdminCreateProcessor::class,
        ),
        new Patch(
            uriTemplate: '/admin/order_returns/{id}',
        ),
        new Post(
            uriTemplate: '/admin/order_returns/{id}/transition',
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_TRANSITION]],
            input: OrderReturnTransitionInput::class,
            read: false,
            processor: OrderReturnTransitionProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/order_returns/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/order_returns',
        ),
        new Get(
            uriTemplate: '/front/account/order_returns/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'object.customer.getId() == user.getId()',
        ),
        new Post(
            uriTemplate: '/front/account/order_returns',
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            processor: OrderReturnFrontCreateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id' => 'exact',
        'ref' => 'partial',
        'orderReturnStatus.code' => 'exact',
        'order.id' => 'exact',
        'order.ref' => 'partial',
        'customer.id' => 'exact',
        'expectedResolution' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'createdAt',
        'updatedAt',
    ],
)]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: [
        'createdAt' => DateFilter::INCLUDE_NULL_BEFORE_AND_AFTER,
    ],
)]
class OrderReturn implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_return:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_return:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_return:write';
    public const GROUP_ADMIN_TRANSITION = 'admin:order_return:transition';
    public const GROUP_FRONT_READ = 'front:order_return:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:order_return:read:single';
    public const GROUP_FRONT_WRITE = 'front:order_return:write';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $ref = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $orderId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $orderRef = null;

    #[Relation(targetResource: Order::class)]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE, self::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_WRITE,
    ])]
    public Order $order;

    // hydrateOutOfGroups: the account endpoints check object.customer even when
    // the return is reached through one of its lines.
    #[Relation(targetResource: Customer::class, hydrateOutOfGroups: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ_SINGLE])]
    public Customer $customer;

    #[Relation(targetResource: OrderReturnStatus::class)]
    #[Column(propelSetter: 'setStatusId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?OrderReturnStatus $orderReturnStatus = null;

    #[Relation(targetResource: OrderReturnReason::class)]
    #[Column(propelSetter: 'setReasonId')]
    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_WRITE,
    ])]
    public ?OrderReturnReason $orderReturnReason = null;

    /**
     * The reason label snapshot, kept even when the reason is later deleted.
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $reasonTitle = null;

    #[Relation(targetResource: OrderReturnLine::class)]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE, self::GROUP_ADMIN_WRITE])]
    #[Groups([
        self::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_WRITE,
    ])]
    public array $orderReturnLines = [];

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_WRITE,
    ])]
    public ?string $expectedResolution = null;

    #[Groups([
        self::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_ADMIN_WRITE,
        self::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_WRITE,
    ])]
    public ?string $customerComment = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $refusalReason = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $refundAmount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?bool $includePostage = false;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?bool $createdByAdmin = false;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getOrderReturnStatus(): ?OrderReturnStatus
    {
        return $this->orderReturnStatus;
    }

    public function setOrderReturnStatus(?OrderReturnStatus $orderReturnStatus): self
    {
        $this->orderReturnStatus = $orderReturnStatus;

        return $this;
    }

    public function getOrderReturnReason(): ?OrderReturnReason
    {
        return $this->orderReturnReason;
    }

    public function setOrderReturnReason(?OrderReturnReason $orderReturnReason): self
    {
        $this->orderReturnReason = $orderReturnReason;

        return $this;
    }

    public function getReasonTitle(): ?string
    {
        return $this->reasonTitle;
    }

    public function setReasonTitle(?string $reasonTitle): self
    {
        $this->reasonTitle = $reasonTitle;

        return $this;
    }

    /**
     * @return array<int, OrderReturnLine>
     */
    public function getOrderReturnLines(): array
    {
        return $this->orderReturnLines;
    }

    /**
     * @param array<int, OrderReturnLine> $orderReturnLines
     */
    public function setOrderReturnLines(array $orderReturnLines): self
    {
        $this->orderReturnLines = $orderReturnLines;

        return $this;
    }

    public function getExpectedResolution(): ?string
    {
        return $this->expectedResolution;
    }

    public function setExpectedResolution(?string $expectedResolution): self
    {
        $this->expectedResolution = $expectedResolution;

        return $this;
    }

    public function getCustomerComment(): ?string
    {
        return $this->customerComment;
    }

    public function setCustomerComment(?string $customerComment): self
    {
        $this->customerComment = $customerComment;

        return $this;
    }

    public function getRefusalReason(): ?string
    {
        return $this->refusalReason;
    }

    public function setRefusalReason(?string $refusalReason): self
    {
        $this->refusalReason = $refusalReason;

        return $this;
    }

    public function getRefundAmount(): ?float
    {
        return $this->refundAmount;
    }

    public function setRefundAmount(?float $refundAmount): self
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    public function getIncludePostage(): ?bool
    {
        return $this->includePostage;
    }

    public function setIncludePostage(?bool $includePostage): self
    {
        $this->includePostage = $includePostage;

        return $this;
    }

    public function getCreatedByAdmin(): ?bool
    {
        return $this->createdByAdmin;
    }

    public function setCreatedByAdmin(?bool $createdByAdmin): self
    {
        $this->createdByAdmin = $createdByAdmin;

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

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(?int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getOrderRef(): ?string
    {
        return $this->orderRef;
    }

    public function setOrderRef(?string $orderRef): self
    {
        $this->orderRef = $orderRef;

        return $this;
    }

    /**
     * The order id and reference are exposed as plain scalars so the account
     * templates can link to the order and label it, without pulling the whole
     * Order resource into the return's serialization groups.
     */
    public function afterModelToResource(array $context): void
    {
        $model = $this->getPropelModel();

        if ($model instanceof OrderReturnModel) {
            $this->orderId = (int) $model->getOrderId();
            $this->orderRef = (string) $model->getOrder()?->getRef();
        }
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderReturnTableMap();
    }
}

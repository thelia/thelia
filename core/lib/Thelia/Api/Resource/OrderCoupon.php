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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\OrderCouponTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_coupons'
        ),
        new Get(
            uriTemplate: '/admin/order_coupons/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_coupons/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_coupons/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
class OrderCoupon implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order_coupon:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order_coupon:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order_coupon:write';

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ])]
    public ?int $id = null;

    #[Relation(targetResource: Order::class)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public ?Order $order;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?string $code;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?string $type;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ,  Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?float $amount;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?string $title;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $shortDescription;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $description;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ])]
    public ?\DateTime $startDate;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?\DateTime $expirationDate;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?bool $isCumulative;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?bool $isRemovingPostage;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?bool $isAvailableOnSpecialOffers;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    #[NotBlank(groups: [Order::GROUP_ADMIN_WRITE])]
    public ?string $serializedConditions;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?bool $perCustomerUsageCount;

    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_WRITE])]
    public ?bool $usageCanceled;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTime $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getIsCumulative(): ?bool
    {
        return $this->isCumulative;
    }

    public function setIsCumulative(?bool $isCumulative): self
    {
        $this->isCumulative = $isCumulative;

        return $this;
    }

    public function getIsRemovingPostage(): ?bool
    {
        return $this->isRemovingPostage;
    }

    public function setIsRemovingPostage(?bool $isRemovingPostage): self
    {
        $this->isRemovingPostage = $isRemovingPostage;

        return $this;
    }

    public function getIsAvailableOnSpecialOffers(): ?bool
    {
        return $this->isAvailableOnSpecialOffers;
    }

    public function setIsAvailableOnSpecialOffers(?bool $isAvailableOnSpecialOffers): self
    {
        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;

        return $this;
    }

    public function getSerializedConditions(): ?string
    {
        return $this->serializedConditions;
    }

    public function setSerializedConditions(?string $serializedConditions): self
    {
        $this->serializedConditions = $serializedConditions;

        return $this;
    }

    public function getPerCustomerUsageCount(): ?bool
    {
        return $this->perCustomerUsageCount;
    }

    public function setPerCustomerUsageCount(?bool $perCustomerUsageCount): self
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    public function getUsageCanceled(): ?bool
    {
        return $this->usageCanceled;
    }

    public function setUsageCanceled(?bool $usageCanceled): self
    {
        $this->usageCanceled = $usageCanceled;

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
        return new OrderCouponTableMap();
    }
}

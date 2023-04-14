<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_coupon'
        ),
        new GetCollection(
            uriTemplate: '/admin/order_coupon'
        ),
        new Get(
            uriTemplate: '/admin/order_coupon/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_coupon/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_coupon/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderCoupon extends AbstractPropelResource
{
    public const GROUP_READ = 'order_coupon:read';
    public const GROUP_READ_SINGLE = 'order_coupon:read:single';
    public const GROUP_WRITE = 'order_coupon:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ])]
    public ?int $orderId;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?string $code;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?string $type;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?float $amount;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?string $title;

    #[Groups([self::GROUP_READ])]
    public ?string $shortDescription;

    #[Groups([self::GROUP_READ])]
    public ?string $description;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?\DateTime $startDate;

    #[Groups([self::GROUP_READ, Order::GROUP_READ])]
    public ?\DateTime $expirationDate;

    #[Groups([self::GROUP_READ])]
    public ?bool $isCumulative;

    #[Groups([self::GROUP_READ])]
    public ?bool $isRemovingPostage;

    #[Groups([self::GROUP_READ])]
    public ?bool $isAvailableOnSpecialOffers;


    public function getId(): ?int
    {
        return $this->id;
    }


    public function setId(?int $id): void
    {
        $this->id = $id;
    }


    public function getOrderId(): ?int
    {
        return $this->orderId;
    }


    public function setOrderId(?int $orderId): void
    {
        $this->orderId = $orderId;
    }


    public function getCode(): ?string
    {
        return $this->code;
    }


    public function setCode(?string $code): void
    {
        $this->code = $code;
    }


    public function getType(): ?string
    {
        return $this->type;
    }


    public function setType(?string $type): void
    {
        $this->type = $type;
    }


    public function getAmount(): ?float
    {
        return $this->amount;
    }


    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }


    public function getTitle(): ?string
    {
        return $this->title;
    }


    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }


    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }


    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }


    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }


    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }


    public function setStartDate(?\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }


    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }



    public function setExpirationDate(?\DateTime $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }


    public function getIsCumulative(): ?string
    {
        return $this->isCumulative;
    }


    public function setIsCumulative(?string $isCumulative): void
    {
        $this->isCumulative = $isCumulative;
    }


    public function getIsRemovingPostage(): ?bool
    {
        return $this->isRemovingPostage;
    }


    public function setIsRemovingPostage(?bool $isRemovingPostage): void
    {
        $this->isRemovingPostage = $isRemovingPostage;
    }


    public function getIsAvailableOnSpecialOffers(): ?bool
    {
        return $this->isAvailableOnSpecialOffers;
    }


    public function setIsAvailableOnSpecialOffers(?bool $isAvailableOnSpecialOffers): void
    {
        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
    }


    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\OrderCoupon::class;
    }
}

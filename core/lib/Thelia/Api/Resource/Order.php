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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order'
        ),
        new GetCollection(
            uriTemplate: '/admin/order'
        ),
        new Get(
            uriTemplate: '/admin/order/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Order extends AbstractPropelResource
{
    public const GROUP_READ = 'order:read';
    public const GROUP_READ_SINGLE = 'order:read:single';
    public const GROUP_WRITE = 'order:write';

    #[Groups([self::GROUP_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $ref;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $discount;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $postage;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $postageTax;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $postageTaxRuleTitle;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $transactionRef;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $invoiceRef;

    #[Relation(targetResource: OrderProduct::class)]
    #[Groups([self::GROUP_READ])]
    public Collection $orderProducts;

    #[Relation(targetResource: OrderCoupon::class)]
    #[Groups([self::GROUP_READ])]
    public Collection $orderCoupons;

    #[Relation(targetResource: OrderAddress::class)]
    #[Groups([self::GROUP_READ])]
    public OrderAddress $invoiceOrderAddress;

    #[Relation(targetResource: OrderAddress::class)]
    #[Groups([self::GROUP_READ])]
    public OrderAddress $deliveryOrderAddress;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_READ])]
    public Module $paymentModule;

    #[Relation(targetResource: OrderStatus::class)]
    #[Groups([self::GROUP_READ])]
    public OrderStatus $orderStatus;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_READ])]
    public Customer $customer;


    public function __construct()
    {
        $this->orderCoupons = new ArrayCollection();
        $this->orderProducts = new ArrayCollection();
    }

    public function getPaymentModule(): Module
    {
        return $this->paymentModule;
    }

    public function setPaymentModule(Module $paymentModule): self
    {
        $this->paymentModule = $paymentModule;
        return $this;
    }


    public function getOrderCoupons(): Collection
    {
        return $this->orderCoupons;
    }


    public function setOrderCoupons(Collection $orderCoupons): self
    {
        $this->orderCoupons = $orderCoupons;
        return $this;
    }

    public function getInvoiceRef(): ?string
    {
        return $this->invoiceRef;
    }

    public function setInvoiceRef(?string $invoiceRef): void
    {
        $this->invoiceRef = $invoiceRef;
    }



    public function getInvoiceOrderAddress(): OrderAddress
    {
        return $this->invoiceOrderAddress;
    }

    public function setInvoiceOrderAddress(OrderAddress $invoiceOrderAddress): void
    {
        $this->invoiceOrderAddress = $invoiceOrderAddress;
    }

    public function getDeliveryOrderAddress(): OrderAddress
    {
        return $this->deliveryOrderAddress;
    }


    public function setDeliveryOrderAddress(OrderAddress $deliveryOrderAddress): void
    {
        $this->deliveryOrderAddress = $deliveryOrderAddress;
    }


    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getTransactionRef(): ?string
    {
        return $this->transactionRef;
    }

    public function setTransactionRef(?string $transactionRef): void
    {
        $this->transactionRef = $transactionRef;
    }

    public function getPostageTax(): string
    {
        return $this->postageTax;
    }

    public function setPostageTax(string $postageTax): void
    {
        $this->postageTax = $postageTax;
    }

    public function getPostageTaxRuleTitle(): ?string
    {
        return $this->postageTaxRuleTitle;
    }

    public function setPostageTaxRuleTitle(?string $postageTaxRuleTitle): void
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    public function setOrderProducts(Collection $orderProducts): self
    {
        $this->orderProducts = $orderProducts;

        return $this;
    }

    public function getOrderStatus(): OrderStatus
    {
        return $this->orderStatus;
    }

    public function setOrderStatus(OrderStatus $orderStatus): self
    {
        $this->orderStatus = $orderStatus;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): void
    {
        $this->discount = $discount;
    }

    public function getPostage(): string
    {
        return $this->postage;
    }

    public function setPostage(string $postage): void
    {
        $this->postage = $postage;
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

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Order::class;
    }
}

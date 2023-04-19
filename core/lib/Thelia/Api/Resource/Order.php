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
use Thelia\Api\Bridge\Propel\Attribute\Column;
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
    denormalizationContext: ['groups' => [self::GROUP_WRITE,I18n::GROUP_WRITE]]
)]
class Order extends AbstractPropelResource
{
    public const GROUP_READ = 'order:read';
    public const GROUP_READ_SINGLE = 'order:read:single';
    public const GROUP_WRITE = 'order:write';

    #[Groups([self::GROUP_READ,OrderCoupon::GROUP_READ_SINGLE,OrderProduct::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $ref;

    #[Groups([self::GROUP_READ_SINGLE,self::GROUP_WRITE])]
    public ?\DateTime $invoiceDate;

    #[Groups([self::GROUP_READ_SINGLE,self::GROUP_WRITE])]
    public ?float $currencyRate;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?float $discount;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public float $postage;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public float $postageTax;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?string $postageTaxRuleTitle;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?string $transactionRef;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?string $deliveryRef;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?string $invoiceRef;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?int $version;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?\DateTime $versionCreatedAt;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public ?string $versionCreatedBy;

    #[Groups([self::GROUP_READ])]
    public ?float $totalAmount;

    #[Relation(targetResource: OrderProduct::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Collection $orderProducts;

    #[Relation(targetResource: OrderCoupon::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Collection $orderCoupons;

    #[Relation(targetResource: OrderAddress::class)]
    #[Column(propelGetter: "getOrderAddressRelatedByInvoiceOrderAddressId")]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public OrderAddress $invoiceOrderAddress;

    #[Relation(targetResource: OrderAddress::class)]
    #[Column(propelGetter: "getOrderAddressRelatedByDeliveryOrderAddressId")]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public OrderAddress $deliveryOrderAddress;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    #[Column(propelGetter: "getModuleRelatedByPaymentModuleId")]
    public Module $paymentModule;

    #[Relation(targetResource: Module::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    #[Column(propelGetter: "getModuleRelatedByDeliveryModuleId")]
    public Module $deliveryModule;

    #[Relation(targetResource: OrderStatus::class)]
    #[Groups([self::GROUP_READ,self::GROUP_WRITE])]
    public OrderStatus $orderStatus;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Customer $customer;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Currency $currency;

    #[Relation(targetResource: Lang::class)]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Lang $lang;

    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public int $cartId;


    public function __construct()
    {
        $this->orderCoupons = new ArrayCollection();
        $this->orderProducts = new ArrayCollection();
    }

    public function getTotalAmount(): ?float
    {
        return $this->getPropelModel()->getTotalAmount();
    }

    public function getCartId(): int
    {
        return $this->cartId;
    }

    public function setCartId(int $cartId): void
    {
        $this->cartId = $cartId;
    }

    public function getLang(): Lang
    {
        return $this->lang;
    }

    public function setLang(Lang $lang): void
    {
        $this->lang = $lang;
    }

    public function getCurrencyRate(): ?float
    {
        return $this->currencyRate;
    }

    public function setCurrencyRate(?float $currencyRate): void
    {
        $this->currencyRate = $currencyRate;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDeliveryRef(): ?string
    {
        return $this->deliveryRef;
    }

    public function setDeliveryRef(?string $deliveryRef): void
    {
        $this->deliveryRef = $deliveryRef;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version): void
    {
        $this->version = $version;
    }

    public function getVersionCreatedAt(): ?\DateTime
    {
        return $this->versionCreatedAt;
    }

    public function setVersionCreatedAt(?\DateTime $versionCreatedAt): void
    {
        $this->versionCreatedAt = $versionCreatedAt;
    }

    public function getVersionCreatedBy(): ?string
    {
        return $this->versionCreatedBy;
    }

    public function setVersionCreatedBy(?string $versionCreatedBy): void
    {
        $this->versionCreatedBy = $versionCreatedBy;
    }

    public function getInvoiceDate(): ?\DateTime
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTime $invoiceDate): void
    {
        $this->invoiceDate = $invoiceDate;
    }

    public function getDeliveryModule(): Module
    {
        return $this->deliveryModule;
    }

    public function setDeliveryModule(Module $deliveryModule): void
    {
        $this->deliveryModule = $deliveryModule;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
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

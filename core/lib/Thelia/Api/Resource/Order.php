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

use ApiPlatform\Metadata\ApiFilter;
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
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\RangeFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/orders'
        ),
        new GetCollection(
            uriTemplate: '/admin/orders'
        ),
        new Get(
            uriTemplate: '/admin/orders/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/orders/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/orders/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE,I18n::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'invoiceOrderAddress.firstname' => 'partial',
        'invoiceOrderAddress.lastname' => 'partial',
        'invoiceOrderAddress.company' => 'partial',
        'paymentModule.code' => 'partial',
        'title' => 'partial',
        'totalAmount',
        'ref' => 'partial',
        'orderStatus.code',
    ]
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'createdAt'
    ]
)]
#[ApiFilter(
    filterClass: RangeFilter::class,
    properties: [
        'discount'
    ]
)]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: ['createdAt' => DateFilter::INCLUDE_NULL_BEFORE_AND_AFTER]
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

    #[Relation(targetResource: OrderAddress::class,relationAlias: "OrderAddressRelatedByInvoiceOrderAddressId")]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public OrderAddress $invoiceOrderAddress;

    #[Relation(targetResource: OrderAddress::class,relationAlias: "OrderAddressRelatedByDeliveryOrderAddressId")]
    #[Groups([self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public OrderAddress $deliveryOrderAddress;

    #[Relation(targetResource: Module::class,relationAlias: "ModuleRelatedByPaymentModuleId")]
    #[Groups([self::GROUP_WRITE])]
    public Module $paymentModule;

    #[Relation(targetResource: Module::class,relationAlias: "ModuleRelatedByDeliveryModuleId")]
    #[Groups([self::GROUP_WRITE])]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Order
    {
        $this->id = $id;
        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): Order
    {
        $this->ref = $ref;
        return $this;
    }

    public function getInvoiceDate(): ?\DateTime
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTime $invoiceDate): Order
    {
        $this->invoiceDate = $invoiceDate;
        return $this;
    }

    public function getCurrencyRate(): ?float
    {
        return $this->currencyRate;
    }

    public function setCurrencyRate(?float $currencyRate): Order
    {
        $this->currencyRate = $currencyRate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): Order
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): Order
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): Order
    {
        $this->discount = $discount;
        return $this;
    }

    public function getPostage(): float
    {
        return $this->postage;
    }

    public function setPostage(float $postage): Order
    {
        $this->postage = $postage;
        return $this;
    }

    public function getPostageTax(): float
    {
        return $this->postageTax;
    }

    public function setPostageTax(float $postageTax): Order
    {
        $this->postageTax = $postageTax;
        return $this;
    }

    public function getPostageTaxRuleTitle(): ?string
    {
        return $this->postageTaxRuleTitle;
    }

    public function setPostageTaxRuleTitle(?string $postageTaxRuleTitle): Order
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;
        return $this;
    }

    public function getTransactionRef(): ?string
    {
        return $this->transactionRef;
    }

    public function setTransactionRef(?string $transactionRef): Order
    {
        $this->transactionRef = $transactionRef;
        return $this;
    }

    public function getDeliveryRef(): ?string
    {
        return $this->deliveryRef;
    }

    public function setDeliveryRef(?string $deliveryRef): Order
    {
        $this->deliveryRef = $deliveryRef;
        return $this;
    }

    public function getInvoiceRef(): ?string
    {
        return $this->invoiceRef;
    }

    public function setInvoiceRef(?string $invoiceRef): Order
    {
        $this->invoiceRef = $invoiceRef;
        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version): Order
    {
        $this->version = $version;
        return $this;
    }

    public function getVersionCreatedAt(): ?\DateTime
    {
        return $this->versionCreatedAt;
    }

    public function setVersionCreatedAt(?\DateTime $versionCreatedAt): Order
    {
        $this->versionCreatedAt = $versionCreatedAt;
        return $this;
    }

    public function getVersionCreatedBy(): ?string
    {
        return $this->versionCreatedBy;
    }

    public function setVersionCreatedBy(?string $versionCreatedBy): Order
    {
        $this->versionCreatedBy = $versionCreatedBy;
        return $this;
    }

    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    public function setOrderProducts(Collection $orderProducts): Order
    {
        $this->orderProducts = $orderProducts;
        return $this;
    }

    public function getOrderCoupons(): Collection
    {
        return $this->orderCoupons;
    }

    public function setOrderCoupons(Collection $orderCoupons): Order
    {
        $this->orderCoupons = $orderCoupons;
        return $this;
    }

    public function getInvoiceOrderAddress(): OrderAddress
    {
        return $this->invoiceOrderAddress;
    }

    public function setInvoiceOrderAddress(OrderAddress $invoiceOrderAddress): Order
    {
        $this->invoiceOrderAddress = $invoiceOrderAddress;
        return $this;
    }

    public function getDeliveryOrderAddress(): OrderAddress
    {
        return $this->deliveryOrderAddress;
    }

    public function setDeliveryOrderAddress(OrderAddress $deliveryOrderAddress): Order
    {
        $this->deliveryOrderAddress = $deliveryOrderAddress;
        return $this;
    }

    public function getPaymentModule(): Module
    {
        return $this->paymentModule;
    }

    public function setPaymentModule(Module $paymentModule): Order
    {
        $this->paymentModule = $paymentModule;
        return $this;
    }

    public function getDeliveryModule(): Module
    {
        return $this->deliveryModule;
    }

    public function setDeliveryModule(Module $deliveryModule): Order
    {
        $this->deliveryModule = $deliveryModule;
        return $this;
    }

    public function getOrderStatus(): OrderStatus
    {
        return $this->orderStatus;
    }

    public function setOrderStatus(OrderStatus $orderStatus): Order
    {
        $this->orderStatus = $orderStatus;
        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): Order
    {
        $this->customer = $customer;
        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): Order
    {
        $this->currency = $currency;
        return $this;
    }

    public function getLang(): Lang
    {
        return $this->lang;
    }

    public function setLang(Lang $lang): Order
    {
        $this->lang = $lang;
        return $this;
    }

    public function getCartId(): int
    {
        return $this->cartId;
    }

    public function setCartId(int $cartId): Order
    {
        $this->cartId = $cartId;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Order::class;
    }
}

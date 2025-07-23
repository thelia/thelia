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
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\DateFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\RangeFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/orders',
        ),
        new GetCollection(
            uriTemplate: '/admin/orders',
        ),
        new Get(
            uriTemplate: '/admin/orders/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/orders/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/orders/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/orders/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/orders',
        ),
        new Get(
            uriTemplate: '/front/account/orders/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'object.customer.getId() == user.getId()',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'invoiceOrderAddress.firstname' => 'partial',
        'invoiceOrderAddress.lastname' => 'partial',
        'invoiceOrderAddress.company' => 'partial',
        'paymentModule.code' => 'partial',
        'title' => 'partial',
        'totalAmount',
        'ref' => 'partial',
        'orderStatus.code',
        'customer.id' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'orderStatus.code',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'createdAt',
    ],
)]
#[ApiFilter(
    filterClass: RangeFilter::class,
    properties: [
        'discount',
    ],
)]
#[ApiFilter(
    filterClass: DateFilter::class,
    properties: ['createdAt' => DateFilter::INCLUDE_NULL_BEFORE_AND_AFTER],
)]
class Order implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:order:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:order:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:order:write';
    public const GROUP_FRONT_READ = 'front:order:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:order:read:single';

    #[Groups([self::GROUP_ADMIN_READ,
        OrderCoupon::GROUP_ADMIN_READ_SINGLE,
        OrderProduct::GROUP_ADMIN_READ_SINGLE,
        self::GROUP_FRONT_READ,
        OrderProduct::GROUP_FRONT_READ,
    ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $ref = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $invoiceDate = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[NotNull]
    public float $currencyRate;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?float $discount = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public float $postage;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public float $postageTax;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $postageTaxRuleTitle = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $transactionRef = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $deliveryRef = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public ?string $invoiceRef = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $totalAmount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $totalAmountWithoutTaxes = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $totalAmountWithTaxBeforeDiscount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $amountDiscountWithTaxes = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $totalAmountWithTaxesAfterDiscount = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $totalShippingWithTaxes = null;

    #[Relation(targetResource: OrderProduct::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    public array $orderProducts = [];

    #[Relation(targetResource: OrderCoupon::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public array $orderCoupons = [];

    #[Relation(targetResource: OrderAddress::class, relationAlias: 'OrderAddressRelatedByInvoiceOrderAddressId')]
    #[Column(propelSetter: 'setInvoiceOrderAddressId')]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public OrderAddress $invoiceOrderAddress;

    #[Relation(targetResource: OrderAddress::class, relationAlias: 'OrderAddressRelatedByDeliveryOrderAddressId')]
    #[Column(propelSetter: 'setDeliveryOrderAddressId')]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public OrderAddress $deliveryOrderAddress;

    #[Relation(targetResource: Module::class, relationAlias: 'ModuleRelatedByPaymentModuleId')]
    #[Column(propelSetter: 'setPaymentModuleId')]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public Module $paymentModule;

    #[Relation(targetResource: Module::class, relationAlias: 'ModuleRelatedByDeliveryModuleId')]
    #[Column(propelSetter: 'setDeliveryModuleId')]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public Module $deliveryModule;

    #[Relation(targetResource: OrderStatus::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Column(propelSetter: 'setStatusId')]
    public OrderStatus $orderStatus;

    #[Relation(targetResource: Customer::class)]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public Customer $customer;

    #[Relation(targetResource: Currency::class)]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public Currency $currency;

    #[Relation(targetResource: Lang::class)]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ_SINGLE])]
    public Lang $lang;

    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public int $cartId;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getTotalAmount(): ?float
    {
        $propelModel = $this->getPropelModel();

        if (!$propelModel instanceof ActiveRecordInterface) {
            $propelModel = OrderQuery::create()->findOneById($this->getId());
            $this->setPropelModel($propelModel);
        }

        return round($propelModel?->getTotalAmount(), 2);
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getTotalAmountWithoutTaxes(): ?float
    {
        $itemsTax = 0;
        /** @var \Thelia\Model\Order $orderPropelModel */
        $orderPropelModel = $this->getPropelModel();

        if (!$orderPropelModel) {
            $orderPropelModel = OrderQuery::create()->findOneById($this->getId());
            $this->setPropelModel($orderPropelModel);
        }

        $itemsAmount = $orderPropelModel->getTotalAmount($itemsTax, false, false);

        return round($itemsAmount - $itemsTax, 2);
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getTotalAmountWithTaxBeforeDiscount(): ?float
    {
        $totalTaxedAmount = $this->getTotalAmount();
        $postage = $this->getPostage();
        $discount = $this->getDiscount();

        return round($totalTaxedAmount - $postage + $discount, 2);
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getAmountDiscountWithTaxes(): ?float
    {
        /** @var \Thelia\Model\Order $orderPropelModel */
        $orderPropelModel = $this->getPropelModel();

        if (!$orderPropelModel) {
            $orderPropelModel = OrderQuery::create()->findOneById($this->getId());
            $this->setPropelModel($orderPropelModel);
        }

        return round($orderPropelModel->getDiscount(), 2);
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getTotalAmountWithTaxesAfterDiscount(): ?float
    {
        $totalTaxedAmount = $this->getTotalAmount();
        $postage = $this->getPostage();

        return round($totalTaxedAmount - $postage, 2);
    }

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public function getTotalShippingWithTaxes(): ?float
    {
        /** @var \Thelia\Model\Order $orderPropelModel */
        $orderPropelModel = $this->getPropelModel();

        if (!$orderPropelModel) {
            $orderPropelModel = OrderQuery::create()->findOneById($this->getId());
            $this->setPropelModel($orderPropelModel);
        }

        return round($orderPropelModel->getPostage(), 2);
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

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getInvoiceDate(): ?\DateTime
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTime $invoiceDate): self
    {
        $this->invoiceDate = $invoiceDate;

        return $this;
    }

    public function getCurrencyRate(): float
    {
        return $this->currencyRate;
    }

    public function setCurrencyRate(float $currencyRate): self
    {
        $this->currencyRate = $currencyRate;

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

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getPostage(): float
    {
        return round($this->postage, 2);
    }

    public function setPostage(float $postage): self
    {
        $this->postage = $postage;

        return $this;
    }

    public function getPostageTax(): float
    {
        return round($this->postageTax, 2);
    }

    public function setPostageTax(float $postageTax): self
    {
        $this->postageTax = $postageTax;

        return $this;
    }

    public function getPostageTaxRuleTitle(): ?string
    {
        return $this->postageTaxRuleTitle;
    }

    public function setPostageTaxRuleTitle(?string $postageTaxRuleTitle): self
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;

        return $this;
    }

    public function getTransactionRef(): ?string
    {
        return $this->transactionRef;
    }

    public function setTransactionRef(?string $transactionRef): self
    {
        $this->transactionRef = $transactionRef;

        return $this;
    }

    public function getDeliveryRef(): ?string
    {
        return $this->deliveryRef;
    }

    public function setDeliveryRef(?string $deliveryRef): self
    {
        $this->deliveryRef = $deliveryRef;

        return $this;
    }

    public function getInvoiceRef(): ?string
    {
        return $this->invoiceRef;
    }

    public function setInvoiceRef(?string $invoiceRef): self
    {
        $this->invoiceRef = $invoiceRef;

        return $this;
    }

    public function getOrderProducts(): array
    {
        return $this->orderProducts;
    }

    public function setOrderProducts(array $orderProducts): self
    {
        $this->orderProducts = $orderProducts;

        return $this;
    }

    public function getOrderCoupons(): array
    {
        return $this->orderCoupons;
    }

    public function setOrderCoupons(array $orderCoupons): self
    {
        $this->orderCoupons = $orderCoupons;

        return $this;
    }

    public function getInvoiceOrderAddress(): OrderAddress
    {
        return $this->invoiceOrderAddress;
    }

    public function setInvoiceOrderAddress(OrderAddress $invoiceOrderAddress): self
    {
        $this->invoiceOrderAddress = $invoiceOrderAddress;

        return $this;
    }

    public function getDeliveryOrderAddress(): OrderAddress
    {
        return $this->deliveryOrderAddress;
    }

    public function setDeliveryOrderAddress(OrderAddress $deliveryOrderAddress): self
    {
        $this->deliveryOrderAddress = $deliveryOrderAddress;

        return $this;
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

    public function getDeliveryModule(): Module
    {
        return $this->deliveryModule;
    }

    public function setDeliveryModule(Module $deliveryModule): self
    {
        $this->deliveryModule = $deliveryModule;

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

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

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

    public function getLang(): Lang
    {
        return $this->lang;
    }

    public function setLang(Lang $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getCartId(): int
    {
        return $this->cartId;
    }

    public function setCartId(int $cartId): self
    {
        $this->cartId = $cartId;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new OrderTableMap();
    }
}

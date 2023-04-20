<?php

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
            uriTemplate: '/admin/cart'
        ),
        new GetCollection(
            uriTemplate: '/admin/cart'
        ),
        new Get(
            uriTemplate: '/admin/cart/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/cart/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/cart/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class Cart extends AbstractPropelResource
{
    public const GROUP_READ = 'cart:read';
    public const GROUP_READ_SINGLE = 'cart:read:single';
    public const GROUP_WRITE = 'cart:write';

    #[Groups([self::GROUP_READ,CartItem::GROUP_READ,Order::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?string $token;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?Customer $customer;

    #[Relation(targetResource: Address::class)]
    #[Column(propelGetter: "getAddressRelatedByAddressDeliveryId")]
    #[Groups([self::GROUP_READ])]
    public ?Address $addressDelivery;//todo getAddressDeliveryId()

    #[Relation(targetResource: Address::class)]
    #[Column(propelGetter: "getAddressRelatedByAddressInvoiceId")]
    #[Groups([self::GROUP_READ])]
    public ?Address $addressInvoice;//todo

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_READ])]
    public ?Currency $currency;

    #[Relation(targetResource: CartItem::class,)]
    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?Collection $cartItems;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?float $discount;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getAddressDelivery(): ?Address
    {
        return $this->addressDelivery;
    }

    public function setAddressDelivery(?Address $addressDelivery): void
    {
        $this->addressDelivery = $addressDelivery;
    }

    public function getAddressInvoice(): ?Address
    {
        return $this->addressInvoice;
    }

    public function setAddressInvoice(?Address $addressInvoice): void
    {
        $this->addressInvoice = $addressInvoice;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): void
    {
        $this->currency = $currency;
    }

    public function getCartItems(): ?Collection
    {
        return $this->cartItems;
    }

    public function setCartItems(?Collection $cartItems): void
    {
        $this->cartItems = $cartItems;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Cart::class;
    }
}

<?php

namespace Thelia\Api\Resource;


use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use DateTime;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/carts'
        ),
        new GetCollection(
            uriTemplate: '/admin/carts'
        ),
        new Get(
            uriTemplate: '/admin/carts/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/carts/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/carts/{id}'
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

    #[Relation(targetResource: Address::class, relationAlias: "AddressRelatedByAddressDeliveryId")]
    #[Groups([self::GROUP_READ])]
    public ?Address $addressDelivery;

    #[Relation(targetResource: Address::class, relationAlias: "AddressRelatedByAddressInvoiceId")]
    #[Groups([self::GROUP_READ])]
    public ?Address $addressInvoice;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_READ])]
    public ?Currency $currency;

    #[Relation(targetResource: CartItem::class,)]
    #[Groups([self::GROUP_READ,Order::GROUP_READ])]
    public ?Collection $cartItems;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public ?float $discount;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?DateTime $updatedAt;

    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Cart
    {
        $this->id = $id;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): Cart
    {
        $this->token = $token;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): Cart
    {
        $this->customer = $customer;
        return $this;
    }

    public function getAddressDelivery(): ?Address
    {
        return $this->addressDelivery;
    }

    public function setAddressDelivery(?Address $addressDelivery): Cart
    {
        $this->addressDelivery = $addressDelivery;
        return $this;
    }

    public function getAddressInvoice(): ?Address
    {
        return $this->addressInvoice;
    }

    public function setAddressInvoice(?Address $addressInvoice): Cart
    {
        $this->addressInvoice = $addressInvoice;
        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): Cart
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCartItems(): ?Collection
    {
        return $this->cartItems;
    }

    public function setCartItems(?Collection $cartItems): Cart
    {
        $this->cartItems = $cartItems;
        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): Cart
    {
        $this->discount = $discount;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): Cart
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): Cart
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Cart::class;
    }
}

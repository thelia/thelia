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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\CartTableMap;

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
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/carts/{id}'
        ),
        new Patch(
            uriTemplate: '/admin/carts/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/carts/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/carts'
        ),
        new GetCollection(
            uriTemplate: '/front/carts'
        ),
        new Get(
            uriTemplate: '/front/carts/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()'
        ),
        new Put(
            uriTemplate: '/front/carts/{id}',
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()'
        ),
        new Delete(
            uriTemplate: '/front/carts/{id}',
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]]
)]
class Cart implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:cart:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:cart:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:cart:write';

    public const GROUP_FRONT_READ = 'front:cart:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:cart:read:single';
    public const GROUP_FRONT_WRITE = 'front:cart:write';

    #[Groups([self::GROUP_ADMIN_READ, CartItem::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $token;

    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?Customer $customer;

    #[Relation(targetResource: Address::class, relationAlias: 'AddressRelatedByAddressDeliveryId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?Address $addressDelivery;

    #[Relation(targetResource: Address::class, relationAlias: 'AddressRelatedByAddressInvoiceId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?Address $addressInvoice;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?Currency $currency;

    #[Relation(targetResource: CartItem::class, )]
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?array $cartItems;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?float $discount;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    public function __construct()
    {
        $this->cartItems = [];
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

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

    public function getAddressDelivery(): ?Address
    {
        return $this->addressDelivery;
    }

    public function setAddressDelivery(?Address $addressDelivery): self
    {
        $this->addressDelivery = $addressDelivery;

        return $this;
    }

    public function getAddressInvoice(): ?Address
    {
        return $this->addressInvoice;
    }

    public function setAddressInvoice(?Address $addressInvoice): self
    {
        $this->addressInvoice = $addressInvoice;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCartItems(): ?array
    {
        return $this->cartItems;
    }

    public function setCartItems(?array $cartItems): self
    {
        $this->cartItems = $cartItems;

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
        return new CartTableMap();
    }
}

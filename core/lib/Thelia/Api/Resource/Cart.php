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
use Thelia\Api\Controller\Front\CartController;
use Thelia\Api\State\Processor\CartCreationProcessor;
use Thelia\Model\Map\CartTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/carts',
        ),
        new GetCollection(
            uriTemplate: '/admin/carts',
        ),
        new Get(
            uriTemplate: '/admin/carts/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/carts/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/carts/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/carts/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/carts',
            processor: CartCreationProcessor::class,
        ),
        new Get(
            uriTemplate: '/front/carts/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()',
        ),
        new Get(
            uriTemplate: '/front/cart',
            controller: CartController::class,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/front/carts/{id}',
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()',
        ),
        new Delete(
            uriTemplate: '/front/carts/{id}',
            security: 'is_granted("ROLE_CUSTOMER") and object.customer.getId() == user.getId()',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
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

    // Not writable from the front: the token is a bearer secret used to restore
    // an anonymous cart from its cookie. Letting the body choose it opens cart
    // fixation, so it is always generated server-side (Cart::preInsert).
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?string $token = null;

    // Not writable from the front: POST /front/carts is anonymous, so a body
    // that names its owner would let anyone file a cart under any account.
    // The cart is bound to its customer server-side, at login.
    #[Relation(targetResource: Customer::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?Customer $customer = null;

    #[Relation(targetResource: CartAddress::class, relationAlias: 'CartAddressRelatedByAddressDeliveryId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?CartAddress $addressDelivery = null;

    #[Relation(targetResource: CartAddress::class, relationAlias: 'CartAddressRelatedByAddressInvoiceId')]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?CartAddress $addressInvoice = null;

    #[Relation(targetResource: Currency::class)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?Currency $currency = null;

    #[Relation(targetResource: CartItem::class, )]
    #[Groups([self::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?array $cartItems = [];

    // Not writable from the front either: the discount is subtracted from the
    // cart total, and it is derived from the customer's own rate.
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?float $discount = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $updatedAt = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?float $totalWithoutTax = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?float $deliveryTax = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?float $taxes = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?float $delivery = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?float $total = null;

    #[Groups([self::GROUP_FRONT_READ_SINGLE])]
    public ?bool $virtual = null;

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

    public function getAddressDelivery(): ?CartAddress
    {
        return $this->addressDelivery;
    }

    public function setAddressDelivery(?CartAddress $addressDelivery): self
    {
        $this->addressDelivery = $addressDelivery;

        return $this;
    }

    public function getAddressInvoice(): ?CartAddress
    {
        return $this->addressInvoice;
    }

    public function setAddressInvoice(?CartAddress $addressInvoice): self
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

    public function getDelivery(): ?float
    {
        return $this->delivery;
    }

    public function setDelivery(?float $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getDeliveryTax(): ?float
    {
        return $this->deliveryTax;
    }

    public function setDeliveryTax(?float $deliveryTax): self
    {
        $this->deliveryTax = $deliveryTax;

        return $this;
    }

    public function getTaxes(): ?float
    {
        return round($this->taxes, 2);
    }

    public function setTaxes(?float $taxes): self
    {
        $this->taxes = $taxes;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getTotalWithoutTax(): ?float
    {
        return $this->totalWithoutTax;
    }

    public function setTotalWithoutTax(?float $totalWithoutTax): self
    {
        $this->totalWithoutTax = $totalWithoutTax;

        return $this;
    }

    public function getVirtual(): ?bool
    {
        return $this->virtual;
    }

    public function setVirtual(?bool $virtual): self
    {
        $this->virtual = $virtual;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CartTableMap();
    }
}

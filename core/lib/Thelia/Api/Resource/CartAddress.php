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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Model\Map\CartAddressTableMap;

/**
 * The cart's own copy of a delivery or invoice address.
 *
 * A cart address is a snapshot: the customer may have picked one of the
 * addresses saved on the account, in which case `address` points back at it,
 * or typed it in at checkout, in which case `address` is null and this row is
 * the only place the address exists. The order is built from this copy.
 *
 * Read-only: cart addresses are written by the checkout, never by the API.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/cart_addresses',
        ),
        new Get(
            uriTemplate: '/admin/cart_addresses/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
    ],
)]
class CartAddress implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:cart_address:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:cart_address:read:single';

    public const GROUP_CART_COMBINED = [
        Cart::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_FRONT_READ_SINGLE,
    ];

    #[Groups([self::GROUP_ADMIN_READ, Cart::GROUP_ADMIN_READ, Cart::GROUP_FRONT_READ])]
    public ?int $id = null;

    /**
     * The customer address this copy was made from, null when the address was
     * typed in at checkout and never saved to the account.
     */
    #[Relation(targetResource: Address::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?Address $address = null;

    #[Groups([self::GROUP_ADMIN_READ, ...self::GROUP_CART_COMBINED])]
    public string $firstname;

    #[Groups([self::GROUP_ADMIN_READ, ...self::GROUP_CART_COMBINED])]
    public string $lastname;

    #[Groups([self::GROUP_ADMIN_READ, ...self::GROUP_CART_COMBINED])]
    public ?string $company = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public string $address1;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?string $address2 = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?string $address3 = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public string $zipcode;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public string $city;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?string $phone = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?string $cellphone = null;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?CustomerTitle $customerTitle = null;

    #[Relation(targetResource: Country::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?Country $country = null;

    #[Relation(targetResource: State::class)]
    #[Groups([self::GROUP_ADMIN_READ_SINGLE, ...self::GROUP_CART_COMBINED])]
    public ?State $state = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE])]
    public ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getAddress1(): string
    {
        return $this->address1;
    }

    public function setAddress1(string $address1): self
    {
        $this->address1 = $address1;

        return $this;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function setAddress2(?string $address2): self
    {
        $this->address2 = $address2;

        return $this;
    }

    public function getAddress3(): ?string
    {
        return $this->address3;
    }

    public function setAddress3(?string $address3): self
    {
        $this->address3 = $address3;

        return $this;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function setZipcode(string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    public function setCellphone(?string $cellphone): self
    {
        $this->cellphone = $cellphone;

        return $this;
    }

    public function getCustomerTitle(): ?CustomerTitle
    {
        return $this->customerTitle;
    }

    public function setCustomerTitle(?CustomerTitle $customerTitle): self
    {
        $this->customerTitle = $customerTitle;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): self
    {
        $this->state = $state;

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
        return new CartAddressTableMap();
    }
}

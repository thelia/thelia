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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Model\Map\OrderAddressTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/order_addresses'
        ),
        new Get(
            uriTemplate: '/admin/order_addresses/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/order_addresses/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/order_addresses/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
class OrderAddress implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'order_address:read';
    public const GROUP_READ_SINGLE = 'order_address:read:single';
    public const GROUP_WRITE = 'order_address:write';

    #[Groups([self::GROUP_READ, Order::GROUP_READ, Order::GROUP_READ_SINGLE,Order::GROUP_WRITE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, Order::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, Order::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $lastname;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $address1;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $address2;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $address3;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $zipcode;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public string $city;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $phone;

    #[Groups([self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $cellphone;

    #[Groups([self::GROUP_READ, Order::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?string $company;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups(groups: [self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public CustomerTitle $customerTitle;

    #[Relation(targetResource: Country::class)]
    #[Groups(groups: [self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups(groups: [self::GROUP_READ, Order::GROUP_READ_SINGLE])]
    public ?State $state = null;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ])]
    public ?\DateTime $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

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

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function setZipcode(?string $zipcode): self
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getCustomerTitle(): CustomerTitle
    {
        return $this->customerTitle;
    }

    public function setCustomerTitle(CustomerTitle $customerTitle): self
    {
        $this->customerTitle = $customerTitle;

        return $this;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): self
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
        return new OrderAddressTableMap();
    }
}

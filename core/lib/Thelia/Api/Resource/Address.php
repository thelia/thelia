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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\LocalizedSearchFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/addresses'
        ),
        new GetCollection(
            uriTemplate: '/admin/addresses'
        ),
        new Get(
            uriTemplate: '/admin/addresses/{id}',
            normalizationContext:  ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/addresses/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/addresses/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ, CustomerTitle::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'label',
        'customer.id' => 'exact'
    ]
)]
class Address extends AbstractPropelResource
{
    public const GROUP_READ = 'address:read';
    public const GROUP_READ_SINGLE = 'address:read:single';
    public const GROUP_WRITE = 'address:write';

    #[Groups([self::GROUP_READ, Customer::GROUP_READ_SINGLE,Cart::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $label;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE,Cart::GROUP_READ_SINGLE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE,Cart::GROUP_READ_SINGLE])]
    public string $lastname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $address1;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $address2;

    #[Groups([self::GROUP_WRITE,self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $address3 = '';

    #[Groups([self::GROUP_WRITE,self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $zipcode = '';

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $company;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $cellphone;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $phone;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $city;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?bool $isDefault;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: Country::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?State $state;

    #[Relation(targetResource: Customer::class)]
    #[Groups(groups: [self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Customer $customer;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups(groups:[self::GROUP_READ, self::GROUP_WRITE])]
    public CustomerTitle $customerTitle;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Address
    {
        $this->id = $id;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): Address
    {
        $this->label = $label;
        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): Address
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): Address
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getAddress1(): string
    {
        return $this->address1;
    }

    public function setAddress1(string $address1): Address
    {
        $this->address1 = $address1;
        return $this;
    }

    public function getAddress2(): string
    {
        return $this->address2;
    }

    public function setAddress2(string $address2): Address
    {
        $this->address2 = $address2;
        return $this;
    }

    public function getAddress3(): string
    {
        return $this->address3;
    }

    public function setAddress3(string $address3): Address
    {
        $this->address3 = $address3;
        return $this;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function setZipcode(string $zipcode): Address
    {
        $this->zipcode = $zipcode;
        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): Address
    {
        $this->company = $company;
        return $this;
    }

    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    public function setCellphone(?string $cellphone): Address
    {
        $this->cellphone = $cellphone;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): Address
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): Address
    {
        $this->city = $city;
        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): Address
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): Address
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): Address
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): Address
    {
        $this->country = $country;
        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): Address
    {
        $this->state = $state;
        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): Address
    {
        $this->customer = $customer;
        return $this;
    }

    public function getCustomerTitle(): CustomerTitle
    {
        return $this->customerTitle;
    }

    public function setCustomerTitle(CustomerTitle $customerTitle): Address
    {
        $this->customerTitle = $customerTitle;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Address::class;
    }
}

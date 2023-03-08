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

    #[Groups([self::GROUP_READ, Customer::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $label;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $lastname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $address1;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $address2;

    #[Groups([self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $address3 = '';

    #[Groups([self::GROUP_WRITE, Customer::GROUP_READ_SINGLE])]
    public string $zipcode = '';

    #[Relation(targetResource: Customer::class)]
    #[Groups(groups: [self::GROUP_READ_SINGLE])]
    public Customer $customer;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups(groups:[self::GROUP_READ])]
    public CustomerTitle $customerTitle;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
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

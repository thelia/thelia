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
            uriTemplate: '/admin/customers'
        ),
        new GetCollection(
            uriTemplate: '/admin/customers'
        ),
        new Get(
            uriTemplate: '/admin/customers/{id}',
            normalizationContext:  ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/customers/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/customers/{id}'
        )
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, I18n::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE, I18n::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'ref',
        'firstname',
        'lastname'
    ]
)]
class Customer extends AbstractPropelResource
{
    public const GROUP_READ = 'customer:read';
    public const GROUP_READ_SINGLE = 'customer:read:single';
    public const GROUP_WRITE = 'customer:write';

    #[Groups([self::GROUP_READ, Address::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Address::GROUP_READ_SINGLE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    public string $lastname;

    #[Groups([self::GROUP_WRITE])]
    public string $email;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups([self::GROUP_READ_SINGLE, Address::GROUP_READ_SINGLE])]
    public CustomerTitle $customerTitle;

    #[Relation(targetResource: Address::class)]
    #[Groups([self::GROUP_READ_SINGLE])]
    public Collection $addresses;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): Customer
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): Customer
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): Customer
    {
        $this->email = $email;
        return $this;
    }

    public function getCustomerTitle(): CustomerTitle
    {
        return $this->customerTitle;
    }

    public function setCustomerTitle(CustomerTitle $customerTitle): Customer
    {
        $this->customerTitle = $customerTitle;
        return $this;
    }

    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function setAddresses(Collection $addresses): Customer
    {
        $this->addresses = $addresses;
        return $this;
    }

    public static function getPropelModelClass(): string
    {
        return \Thelia\Model\Customer::class;
    }
}

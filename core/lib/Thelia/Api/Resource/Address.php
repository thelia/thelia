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
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\AddressTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/addresses',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new GetCollection(
            uriTemplate: '/admin/addresses'
        ),
        new Get(
            uriTemplate: '/admin/addresses/{id}',
            normalizationContext: ['groups' => [self::GROUP_READ, self::GROUP_READ_SINGLE]]
        ),
        new Put(
            uriTemplate: '/admin/addresses/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/addresses/{id}'
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ, CustomerTitle::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]]
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'label',
        'customer.id' => 'exact',
    ]
)]
class Address implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_READ = 'address:read';
    public const GROUP_READ_SINGLE = 'address:read:single';
    public const GROUP_WRITE = 'address:write';

    #[Groups([self::GROUP_READ, Customer::GROUP_READ_SINGLE, Cart::GROUP_READ_SINGLE])]
    public ?int $id = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public string $label;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE, Cart::GROUP_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public string $firstname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE, Cart::GROUP_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public string $lastname;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public string $address1;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $address2;

    #[Groups([self::GROUP_WRITE, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public string $address3;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public string $zipcode;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $company;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $cellphone;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?string $phone;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public ?string $city;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?bool $isDefault;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $createdAt;

    #[Groups([self::GROUP_READ_SINGLE])]
    public ?\DateTime $updatedAt;

    #[Relation(targetResource: Country::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    #[NotBlank(groups: [self::GROUP_WRITE, Customer::GROUP_WRITE])]
    public Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_READ_SINGLE, Customer::GROUP_WRITE])]
    public ?State $state;

    #[Relation(targetResource: Customer::class)]
    #[Groups(groups: [self::GROUP_READ_SINGLE, self::GROUP_WRITE])]
    public Customer $customer;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups(groups: [self::GROUP_READ, self::GROUP_WRITE, Customer::GROUP_WRITE])]
    #[Column(propelSetter: 'setTitleId')]
    public CustomerTitle $customerTitle;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

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

    public function getAddress2(): string
    {
        return $this->address2;
    }

    public function setAddress2(string $address2): self
    {
        $this->address2 = $address2;

        return $this;
    }

    public function getAddress3(): string
    {
        return $this->address3;
    }

    public function setAddress3(string $address3): self
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;

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

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

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

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new AddressTableMap();
    }

    #[Callback(groups: [Address::GROUP_WRITE, Customer::GROUP_WRITE])]
    public function verifyZipcode(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();

        if (isset($resource->country) && null !== $country = $resource->getCountry()?->getPropelModel()) {
            if ($country->getNeedZipCode()) {
                $zipCodeRegExp = $country->getZipCodeRE();
                if (null !== $zipCodeRegExp) {
                    if (!preg_match($zipCodeRegExp, $resource->getZipcode())) {
                        $context->addViolation(
                            Translator::getInstance()->trans(
                                'This zip code should respect the following format : %format.',
                                ['%format' => $country->getZipCodeFormat()],null,'en_US'
                            )
                        );
                    }
                }
            }
        }
    }

    #[Callback(groups: [Address::GROUP_WRITE, Customer::GROUP_WRITE])]
    public function verifyState(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();

        if (isset($resource->country) && null !== $country = $resource->getCountry()->getPropelModel()) {
            if ($country->getHasStates()) {
                if (null !== $state = $resource->getState()->getPropelModel()) {
                    if ($state->getCountryId() !== $country->getId()) {
                        $context->addViolation(
                            Translator::getInstance()->trans(
                                "This state doesn't belong to this country.",[],null,'en_US'
                            )
                        );
                    }
                } else {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            'You should select a state for this country.',[],null,'en_US'
                        )
                    );
                }
            }
        }
    }
}

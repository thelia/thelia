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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Filter\BooleanFilter;
use Thelia\Api\Bridge\Propel\Filter\NotInFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\State\Processor\CustomerAddressProcessor;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Legal\CompanyIdentifier;
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Model\Map\AddressTableMap;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/addresses',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new GetCollection(
            uriTemplate: '/admin/addresses',
        ),
        new Get(
            uriTemplate: '/admin/addresses/{id}',
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE]],
        ),
        new Put(
            uriTemplate: '/admin/addresses/{id}',
        ),
        new Patch(
            uriTemplate: '/admin/addresses/{id}',
        ),
        new Delete(
            uriTemplate: '/admin/addresses/{id}',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ, CustomerTitle::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/account/addresses',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            processor: CustomerAddressProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/front/account/addresses',
        ),
        new Get(
            uriTemplate: '/front/account/addresses/{id}',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ, self::GROUP_FRONT_READ_SINGLE]],
            security: 'object.customer.getId() == user.getId()',
        ),
        new Put(
            uriTemplate: '/front/account/addresses/{id}',
            security: 'object.customer.getId() == user.getId()',
            processor: CustomerAddressProcessor::class,
        ),
        new Delete(
            uriTemplate: '/front/account/addresses/{id}',
            security: 'object.customer.getId() == user.getId()',
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'label',
        'customer.id',
    ],
)]
#[ApiFilter(
    filterClass: NotInFilter::class,
    properties: [
        'id',
    ],
)]
#[ApiFilter(
    filterClass: BooleanFilter::class,
    properties: [
        'isDefault',
    ],
)]
class Address implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:address:read';
    public const GROUP_ADMIN_READ_SINGLE = 'admin:address:read:single';
    public const GROUP_ADMIN_WRITE = 'admin:address:write';
    public const GROUP_FRONT_READ = 'front:address:read';
    public const GROUP_FRONT_READ_SINGLE = 'front:address:read:single';
    public const GROUP_FRONT_WRITE = 'front:address:write';
    public const GROUP_ADMIN_COMBINED = [
        self::GROUP_ADMIN_READ,
        self::GROUP_ADMIN_WRITE,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Customer::GROUP_ADMIN_WRITE,
    ];
    public const GROUP_FRONT_COMBINED = [
        self::GROUP_FRONT_READ,
        self::GROUP_FRONT_READ_SINGLE,
        self::GROUP_FRONT_WRITE,
    ];

    #[Groups([
        self::GROUP_ADMIN_READ,
        self::GROUP_FRONT_READ,
        Customer::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_ADMIN_READ_SINGLE,
        Cart::GROUP_FRONT_READ_SINGLE,
        Customer::GROUP_ADMIN_WRITE_UPDATE,
    ])]
    public ?int $id = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public string $label;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED, Cart::GROUP_ADMIN_READ_SINGLE, Cart::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public string $firstname;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED, Cart::GROUP_ADMIN_READ_SINGLE, Cart::GROUP_FRONT_READ_SINGLE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public string $lastname;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public string $address1;

    // The column is not nullable and has no default: left uninitialized, it is
    // skipped by the transformer and the insert fails on the database side.
    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public string $address2 = '';

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public string $address3 = '';

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public string $zipcode;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?string $company = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?string $siret = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?string $vatNumber = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?string $cellphone = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?string $phone = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public ?string $city = null;

    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?bool $isDefault = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ_SINGLE, self::GROUP_FRONT_READ_SINGLE])]
    public ?\DateTime $updatedAt = null;

    #[Relation(targetResource: Country::class)]
    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public Country $country;

    #[Relation(targetResource: State::class)]
    #[Groups([...self::GROUP_ADMIN_COMBINED, ...self::GROUP_FRONT_COMBINED])]
    public ?State $state = null;

    // The front never sets the owner: CustomerAddressProcessor takes it from
    // the token, so an account endpoint cannot write into another address book.
    #[Relation(targetResource: Customer::class)]
    #[Groups(groups: [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_READ_SINGLE, self::GROUP_ADMIN_WRITE])]
    public Customer $customer;

    #[Relation(targetResource: CustomerTitle::class)]
    #[Groups(groups: [self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, Customer::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
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

    // Normalized when read rather than when written: the deserializer sets the properties in
    // whatever order the payload lists them, so `company` is only reliably known once the
    // whole resource has been populated.
    public function getSiret(): ?string
    {
        return CompanyIdentifier::forCompany($this->company, CompanyIdentifier::normalizeSiret($this->siret));
    }

    public function setSiret(?string $siret): self
    {
        $this->siret = $siret;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return CompanyIdentifier::forCompany($this->company, CompanyIdentifier::normalizeVatNumber($this->vatNumber));
    }

    public function setVatNumber(?string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;

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

    /**
     * Same rules as the address forms, from the same place: both identifiers are required as
     * soon as a company name is given, and the checks narrow with the country of the address.
     */
    // GROUP_FRONT_WRITE included on purpose: /api/front/account/addresses writes the same
    // table as the address form, and would otherwise accept what the form refuses.
    #[Callback(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public function verifyLegalIdentifiers(ExecutionContextInterface $context): void
    {
        /** @var self $resource */
        $resource = $context->getRoot();

        $violations = CompanyIdentifierRules::violationsFor(
            $resource->company,
            $resource->siret,
            $resource->vatNumber,
            isset($resource->country) ? $resource->getCountry()?->getPropelModel()?->getIsoalpha2() : null,
        );

        foreach ($violations as $violation) {
            $context
                ->buildViolation(Translator::getInstance()->trans($violation->message, $violation->parameters, null, 'en_US'))
                ->atPath($violation->field)
                ->addViolation();
        }
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public function verifyZipcode(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();

        if (isset($resource->country) && null !== ($country = $resource->getCountry()?->getPropelModel()) && $country->getNeedZipCode()) {
            $zipCodeRegExp = $country->getZipCodeRE();

            if (null !== $zipCodeRegExp && !preg_match($zipCodeRegExp, $resource->getZipcode())) {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        'This zip code should respect the following format : %format.',
                        ['%format' => $country->getZipCodeFormat()],
                        null,
                        'en_US',
                    ),
                );
            }
        }
    }

    #[Callback(groups: [self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_WRITE, Customer::GROUP_ADMIN_WRITE])]
    public function verifyState(ExecutionContextInterface $context): void
    {
        $resource = $context->getRoot();

        if (!isset($resource->country) || null === $country = $resource->getCountry()?->getPropelModel()) {
            return;
        }

        $state = $resource->getState()?->getPropelModel();

        // A state stays tied to its country whether or not the country requires one:
        // an optional department must not be kept when the address moves elsewhere.
        if (null !== $state) {
            if ($state->getCountryId() !== $country->getId()) {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        "This state doesn't belong to this country.",
                        [],
                        null,
                        'en_US',
                    ),
                );
            }

            return;
        }

        // Requiring a state from a country that carries none would reject every address
        // with an error the caller cannot act on.
        if ($country->getHasStates() && $country->hasSelectableStates()) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'You should select a state for this country.',
                    [],
                    null,
                    'en_US',
                ),
            );
        }
    }
}

<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource]
class PickupLocationAddress
{
    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $id;

    protected bool $isDefault;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $label;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $title;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $firstName;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $lastName;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $cellphoneNumber;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $phoneNumber;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $company;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $address1;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $address2;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $address3;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $zipCode;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $city;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected string $countryCode;

    #[Groups([
        DeliveryPickupLocation::GROUP_FRONT_READ,
    ])]
    protected ?array $additionalData;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): PickupLocationAddress
    {
        $this->label = $label;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): PickupLocationAddress
    {
        $this->title = $title;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): PickupLocationAddress
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): PickupLocationAddress
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getCellphoneNumber(): string
    {
        return $this->cellphoneNumber;
    }

    public function setCellphoneNumber(string $cellphoneNumber): PickupLocationAddress
    {
        $this->cellphoneNumber = $cellphoneNumber;

        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): PickupLocationAddress
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function setCompany(string $company): PickupLocationAddress
    {
        $this->company = $company;

        return $this;
    }

    public function getAddress1(): string
    {
        return $this->address1;
    }

    public function setAddress1(string $address1): PickupLocationAddress
    {
        $this->address1 = $address1;

        return $this;
    }

    public function getAddress2(): string
    {
        return $this->address2;
    }

    public function setAddress2(string $address2): PickupLocationAddress
    {
        $this->address2 = $address2;

        return $this;
    }

    public function getAddress3(): string
    {
        return $this->address3;
    }

    public function setAddress3(string $address3): PickupLocationAddress
    {
        $this->address3 = $address3;

        return $this;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): PickupLocationAddress
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): PickupLocationAddress
    {
        $this->city = $city;

        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): PickupLocationAddress
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(?array $additionalData): PickupLocationAddress
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}

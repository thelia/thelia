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

namespace Thelia\Core\Event\Customer;

/**
 * Class CustomerCreateOrUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerCreateOrUpdateEvent extends CustomerEvent
{
    protected bool $emailUpdateAllowed = true;
    protected bool $notifyCustomerOfAccountCreation = false;
    protected bool $notifyCustomerOfAccountModification = true;

    public function __construct(
        protected ?int $title = null,
        protected ?string $firstname = null,
        protected ?string $lastname = null,
        protected ?string $address1 = null,
        protected ?string $address2 = null,
        protected ?string $address3 = null,
        protected ?string $phone = null,
        protected ?string $cellphone = null,
        protected ?string $zipcode = null,
        protected ?string $city = null,
        protected ?string $country = null,
        protected ?string $email = null,
        protected ?string $password = null,
        protected ?int $langId = null,
        protected ?bool $reseller = null,
        protected ?int $sponsor = null,
        protected ?float $discount = null,
        protected ?string $company = null,
        protected ?string $ref = null,
        protected ?int $state = null,
    ) {
        parent::__construct();
    }

    public function setTitle(?int $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function setAddress1(?string $address1): self
    {
        $this->address1 = $address1;

        return $this;
    }

    public function setAddress2(?string $address2): self
    {
        $this->address2 = $address2;

        return $this;
    }

    public function setAddress3(?string $address3): self
    {
        $this->address3 = $address3;

        return $this;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function setCellphone(?string $cellphone): self
    {
        $this->cellphone = $cellphone;

        return $this;
    }

    public function setZipcode(?string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setLangId(?int $langId): self
    {
        $this->langId = $langId;

        return $this;
    }

    public function setReseller(?bool $reseller): self
    {
        $this->reseller = $reseller;

        return $this;
    }

    public function setSponsor(?int $sponsor): self
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    public function setDiscount(?float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function setState(?int $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function getAddress3(): ?string
    {
        return $this->address3;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLangId(): ?int
    {
        return $this->langId;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    public function getTitle(): ?int
    {
        return $this->title;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function getReseller(): ?bool
    {
        return $this->reseller;
    }

    public function getSponsor(): ?int
    {
        return $this->sponsor;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setEmailUpdateAllowed(bool $emailUpdateAllowed): static
    {
        $this->emailUpdateAllowed = $emailUpdateAllowed;

        return $this;
    }

    public function getEmailUpdateAllowed(): bool
    {
        return $this->emailUpdateAllowed;
    }

    public function setNotifyCustomerOfAccountCreation(bool $notifyCustomerOfAccountCreation): static
    {
        $this->notifyCustomerOfAccountCreation = $notifyCustomerOfAccountCreation;

        return $this;
    }

    public function getNotifyCustomerOfAccountCreation(): bool
    {
        return $this->notifyCustomerOfAccountCreation;
    }

    public function getNotifyCustomerOfAccountModification(): bool
    {
        return $this->notifyCustomerOfAccountModification;
    }

    public function setNotifyCustomerOfAccountModification(bool $notifyCustomerOfAccountModification): static
    {
        $this->notifyCustomerOfAccountModification = $notifyCustomerOfAccountModification;

        return $this;
    }
}

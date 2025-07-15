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
    protected bool $emailUpdateAllowed;
    protected bool $notifyCustomerOfAccountCreation;
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

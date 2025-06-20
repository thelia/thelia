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




















    protected $emailUpdateAllowed;

    /** @var bool */
    protected $notifyCustomerOfAccountCreation;

    /** @var bool */
    protected $notifyCustomerOfAccountModification = true;

    /**
     * @param int      $title    the title customer id
     * @param int      $country  the country id
     * @param string   $password plain password, don't put hash password, it will hashes again
     * @param int      $reseller if customer is a reseller
     * @param int      $sponsor  customer's id sponsor
     * @param int|null $state    thre State ID
     */
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
        protected ?int $reseller = null,
        protected ?int $sponsor = null,
        protected ?float $discount = null,
        protected ?string $company = null,
        protected ?string $ref = null,
        protected ?int $state = null
    ) {
        parent::__construct();
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * @return string
     */
    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    /**
     * @return string
     */
    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    /**
     * @return string
     */
    public function getAddress3(): ?string
    {
        return $this->address3;
    }

    /**
     * @return int
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLangId(): ?int
    {
        return $this->langId;
    }

    /**
     * @return string
     */
    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    /**
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @return string
     */
    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    /**
     * @return int
     */
    public function getTitle(): ?int
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    /**
     * @return string
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @return float
     */
    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    /**
     * @return int
     */
    public function getReseller(): ?int
    {
        return $this->reseller;
    }

    /**
     * @return int
     */
    public function getSponsor(): ?int
    {
        return $this->sponsor;
    }

    /**
     * @return string
     */
    public function getRef(): ?string
    {
        return $this->ref;
    }

    /**
     * @return $this
     */
    public function setEmailUpdateAllowed($emailUpdateAllowed): static
    {
        $this->emailUpdateAllowed = $emailUpdateAllowed;

        return $this;
    }

    public function getEmailUpdateAllowed()
    {
        return $this->emailUpdateAllowed;
    }

    /**
     * @param bool $notifyCustomerOfAccountCreation
     *
     * @return $this
     */
    public function setNotifyCustomerOfAccountCreation($notifyCustomerOfAccountCreation): static
    {
        $this->notifyCustomerOfAccountCreation = $notifyCustomerOfAccountCreation;

        return $this;
    }

    /**
     * @return bool
     */
    public function getNotifyCustomerOfAccountCreation()
    {
        return $this->notifyCustomerOfAccountCreation;
    }

    /**
     * @return bool
     */
    public function getNotifyCustomerOfAccountModification()
    {
        return $this->notifyCustomerOfAccountModification;
    }

    /**
     * @param bool $notifyCustomerOfAccountModification
     *
     * @return $this
     */
    public function setNotifyCustomerOfAccountModification($notifyCustomerOfAccountModification): static
    {
        $this->notifyCustomerOfAccountModification = $notifyCustomerOfAccountModification;

        return $this;
    }
}

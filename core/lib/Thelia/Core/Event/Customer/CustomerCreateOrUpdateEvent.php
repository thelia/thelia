<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Customer;

/**
 * Class CustomerCreateOrUpdateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerCreateOrUpdateEvent extends CustomerEvent
{
    //base parameters for creating new customer
    protected $title;
    protected $firstname;
    protected $lastname;
    protected $address1;
    protected $address2;
    protected $address3;
    protected $phone;
    protected $cellphone;
    protected $zipcode;
    protected $city;
    protected $country;
    protected $state;
    protected $email;
    protected $password;
    protected $lang;
    protected $reseller;
    protected $sponsor;
    protected $discount;
    protected $company;
    protected $ref;
    protected $emailUpdateAllowed;
    protected $notifyCustomerOfAccountCreation;

    /**
     * @param int    $title     the title customer id
     * @param string $firstname
     * @param string $lastname
     * @param string $address1
     * @param string $address2
     * @param string $address3
     * @param string $phone
     * @param string $cellphone
     * @param string $zipcode
     * @param string $city
     * @param int    $country   the country id
     * @param string $email
     * @param string $password  plain password, don't put hash password, it will hashes again
     * @param $lang
     * @param int    $reseller  if customer is a reseller
     * @param int    $sponsor   customer's id sponsor
     * @param float  $discount
     * @param string $company
     * @param string $ref
     */
    public function __construct(
        $title,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $address3,
        $phone,
        $cellphone,
        $zipcode,
        $city,
        $country,
        $email,
        $password,
        $lang,
        $reseller,
        $sponsor,
        $discount,
        $company,
        $ref,
        $state = null
    ) {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->country = $country;
        $this->state = $state;
        $this->email = $email;
        $this->firstname = $firstname;
        $this->lang = $lang;
        $this->lastname = $lastname;
        $this->password = $password;
        $this->phone = $phone;
        $this->cellphone = $cellphone;
        $this->title = $title;
        $this->zipcode = $zipcode;
        $this->city = $city;
        $this->reseller = $reseller;
        $this->sponsor = $sponsor;
        $this->discount = $discount;
        $this->company = $company;
        $this->ref = $ref;
    }

    /**
     * @return mixed
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * @return string
     */
    public function getAddress3()
    {
        return $this->address3;
    }

    /**
     * @return int
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return int|null
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param  string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return string
     */
    public function getCellphone()
    {
        return $this->cellphone;
    }

    /**
     * @return int
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return float
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * @return int
     */
    public function getReseller()
    {
        return $this->reseller;
    }

    /**
     * @return int
     */
    public function getSponsor()
    {
        return $this->sponsor;
    }

    /**
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param  mixed $emailUpdateAllowed
     * @return $this
     */
    public function setEmailUpdateAllowed($emailUpdateAllowed)
    {
        $this->emailUpdateAllowed = $emailUpdateAllowed;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmailUpdateAllowed()
    {
        return $this->emailUpdateAllowed;
    }

    /**
     * @param  bool  $notifyCustomerOfAccountCreation
     * @return $this
     */
    public function setNotifyCustomerOfAccountCreation($notifyCustomerOfAccountCreation)
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
}

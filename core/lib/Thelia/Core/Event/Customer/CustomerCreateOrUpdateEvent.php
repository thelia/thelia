<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\Event\Customer;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Model\Customer;

/**
 * Class CustomerCreateOrUpdateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
    protected $email;
    protected $password;
    protected $lang;
    protected $reseller;
    protected $sponsor;
    protected $discount;
    protected $company;

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
     * @param int    $reseller if customer is a reseller
     * @param int    $sponsor  customer's id sponsor
     * @param float  $discount
     * @param string $company
     */
    public function __construct($title, $firstname, $lastname, $address1, $address2, $address3, $phone, $cellphone, $zipcode, $city, $country, $email, $password, $lang, $reseller, $sponsor, $discount, $company)
    {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->country = $country;
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
}

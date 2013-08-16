<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 16/08/13
 * Time: 10:24
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\Event;


class CustomerCreateEvent {
    protected $title;
    protected $firstname;
    protected $lastname;
    protected $address1;
    protected $address2;
    protected $address3;
    protected $phone;
    protected $zipcode;
    protected $country;
    protected $email;
    protected $password;
    protected $lang;

    /**
     * @param int $title the title customer id
     * @param string $firstname
     * @param string $lastname
     * @param string $address1
     * @param string $address2
     * @param string $address3
     * @param string $phone
     * @param string $zipcode
     * @param int $country the country id
     * @param string $email
     * @param string $password plain password, don't put hash password, it will hashes again
     * @param $lang
     */
    function __construct($title, $firstname, $lastname, $address1, $address2, $address3, $phone, $zipcode, $country, $email, $password, $lang)
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
        $this->title = $title;
        $this->zipcode = $zipcode;
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

    

}
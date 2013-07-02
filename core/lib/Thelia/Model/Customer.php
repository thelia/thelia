<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseCustomer;


/**
 * Skeleton subclass for representing a row from the 'customer' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class Customer extends BaseCustomer
{
    public function create($titleId, $firstname, $lastname, $address1, $address2, $address3, $phone, $cellphone, $zipcode, $countryId, $email, $plainPassword, $reseller = 0, $sponsor = null, $discount = 0 )
    {
        $this
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setAddress1($address1)
            ->setAddress2($address2)
            ->setAddress3($address3)
            ->setPhone($phone)
            ->setCellphone($cellphone)
            ->setZipcode($zipcode)
            ->setCountryId($countryId)
            ->setEmail($email)
            ->setPassword($plainPassword)
            ->setReseller($reseller)
            ->setSponsor($sponsor)
            ->setDiscount($discount)
        ;

    }

    public function setPassword($password)
    {
        $this->setAlgo("PASSWORD_BCRYPT");
        return parent::setPassword(password_hash($password, PASSWORD_BCRYPT));
    }
}

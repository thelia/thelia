<?php

namespace Thelia\Model;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CustomRefEvent;
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

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    public function createOrUpdate($titleId, $firstname, $lastname, $address1, $address2, $address3, $phone, $cellphone, $zipcode, $countryId, $email, $plainPassword, $reseller = 0, $sponsor = null, $discount = 0 )
    {
        $this
            ->setCustomerTitleId($titleId)
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
            ->save()
        ;

    }

    public function preInsert(\PropelPDO $con = null)
    {
        $customeRef = new CustomRefEvent($this);
        if (!is_null($this->dispatcher)) {
            $customeRef = new CustomRefEvent($this);
            $this->dispatcher->dispatch("customer.creation.customref", $customeRef);
        }

        $this->setRef($customeRef->hasRef()? $customeRef->getRef() : $this->generateRef());

        return false;
    }

    protected function generateRef()
    {
        return date("YmdHI");
    }

    public function setPassword($password)
    {
        $this->setAlgo("PASSWORD_BCRYPT");
        return parent::setPassword(password_hash($password, PASSWORD_BCRYPT));
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
}

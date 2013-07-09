<?php

namespace Thelia\Model;

use Thelia\Model\Base\Customer as BaseCustomer;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Thelia\Core\Event\CustomRefEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\User\UserInterface;

use Propel\Runtime\Connection\ConnectionInterface;

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
class Customer extends BaseCustomer implements UserInterface
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @param int $titleId customer title id (from customer_title table)
     * @param string $firstname customer first name
     * @param string $lastname customer last name
     * @param string $address1 customer address
     * @param string $address2 customer adress complement 1
     * @param string $address3 customer adress complement 2
     * @param string $phone customer phone number
     * @param string $cellphone customer cellphone number
     * @param string $zipcode customer zipcode
     * @param int $countryId customer country id (from Country table)
     * @param string $email customer email, must be unique
     * @param string $plainPassword customer plain password, hash is made calling setPassword method. Not mandatory parameter but an exception is thrown if customer is new without password
     * @param string $lang
     * @param int $reseller
     * @param null $sponsor
     * @param int $discount
     */
    public function createOrUpdate($titleId, $firstname, $lastname, $address1, $address2, $address3, $phone, $cellphone, $zipcode, $countryId, $email, $plainPassword = null, $lang = null, $reseller = 0, $sponsor = null, $discount = 0)
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
        ;

        if(!is_null($lang)) {
            $this->setLang($lang);
        }

        $this->save();

    }

    public function preInsert(ConnectionInterface $con = null)
    {
        $customerRef = new CustomRefEvent($this);
        if (!is_null($this->dispatcher)) {
            $this->dispatcher->dispatch(TheliaEvents::CREATECUSTOMER_CUSTOMREF, $customerRef);
        }

        $this->setRef($customerRef->hasRef()? $customerRef->getRef() : $this->generateRef());

        return true;
    }

    protected function generateRef()
    {
        return date("YmdHI");
    }

    public function setPassword($password)
    {
        \Thelia\Log\Tlog::getInstance()->debug($password);
        if ($this->isNew() && ($password === null || trim($password) == "")) {
            throw new InvalidArgumentException("customer password is mandatory on creation");
        }

        if($password !== null && trim($password) != "") {
            $this->setAlgo("PASSWORD_BCRYPT");
            return parent::setPassword(password_hash($password, PASSWORD_BCRYPT));
        }

    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritDoc}
     */

    public function getUsername() {
    	return $this->getEmail();
    }

    /**
     * {@inheritDoc}
     */
    public function eraseCredentials() {
    	$this->setPassword(null);
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles() {
    	return array(new Role('ROLE_CUSTOMER'));
    }
}

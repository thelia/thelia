<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\Security\Role\Role;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\Customer as BaseCustomer;
use Thelia\Model\Exception\InvalidArgumentException;
use Thelia\Model\Map\CustomerTableMap;

/**
 * Skeleton subclass for representing a row from the 'customer' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Customer extends BaseCustomer implements UserInterface
{
    /**
     * @param int    $titleId          customer title id (from customer_title table)
     * @param string $firstname        customer first name
     * @param string $lastname         customer last name
     * @param string $address1         customer address
     * @param string $address2         customer adress complement 1
     * @param string $address3         customer adress complement 2
     * @param string $phone            customer phone number
     * @param string $cellphone        customer cellphone number
     * @param string $zipcode          customer zipcode
     * @param string $city
     * @param int    $countryId        customer country id (from Country table)
     * @param string $email            customer email, must be unique
     * @param string $plainPassword    customer plain password, hash is made calling setPassword method. Not mandatory parameter but an exception is thrown if customer is new without password
     * @param int    $lang
     * @param int    $reseller
     * @param null   $sponsor
     * @param int    $discount
     * @param null   $company
     * @param null   $ref
     * @param bool   $forceEmailUpdate true if the email address could be updated
     * @param int    $stateId          customer state id (from State table)
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createOrUpdate(
        $titleId,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $address3,
        $phone,
        $cellphone,
        $zipcode,
        $city,
        $countryId,
        $email = null,
        $plainPassword = null,
        $lang = null,
        $reseller = 0,
        $sponsor = null,
        $discount = 0,
        $company = null,
        $ref = null,
        $forceEmailUpdate = false,
        $stateId = null
    ): void {
        $this
            ->setTitleId($titleId)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setEmail($email, $forceEmailUpdate)
            ->setPassword($plainPassword)
            ->setReseller($reseller)
            ->setSponsor($sponsor)
            ->setDiscount($discount)
            ->setRef($ref)
        ;

        if (null !== $lang) {
            $this->setLangId($lang);
        }

        $con = Propel::getWriteConnection(CustomerTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            if ($this->isNew()) {
                $address = new Address();

                $address
                    ->setLabel(Translator::getInstance()->trans('Main address'))
                    ->setCompany($company)
                    ->setTitleId($titleId)
                    ->setFirstname($firstname)
                    ->setLastname($lastname)
                    ->setAddress1($address1)
                    ->setAddress2($address2)
                    ->setAddress3($address3)
                    ->setPhone($phone)
                    ->setCellphone($cellphone)
                    ->setZipcode($zipcode)
                    ->setCity($city)
                    ->setCountryId($countryId)
                    ->setStateId($stateId)
                    ->setIsDefault(1)
                ;

                $this->addAddress($address);

                if (ConfigQuery::isCustomerEmailConfirmationEnable()) {
                    $this->setConfirmationToken(bin2hex(random_bytes(32)));
                }
            } else {
                $address = $this->getDefaultAddress();

                $address
                    ->setCompany($company)
                    ->setTitleId($titleId)
                    ->setFirstname($firstname)
                    ->setLastname($lastname)
                    ->setAddress1($address1)
                    ->setAddress2($address2)
                    ->setAddress3($address3)
                    ->setPhone($phone)
                    ->setCellphone($cellphone)
                    ->setZipcode($zipcode)
                    ->setCity($city)
                    ->setCountryId($countryId)
                    ->setStateId($stateId)
                    ->save($con)
                ;
            }
            $this->save($con);

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Return the customer lang, or the default one if none is defined.
     *
     * @return \Thelia\Model\Lang Lang model
     */
    public function getCustomerLang()
    {
        $lang = $this->getLangModel();

        if ($lang === null) {
            $lang = (new LangQuery())
                ->filterByByDefault(1)
                ->findOne()
            ;
        }

        return $lang;
    }

    /**
     * Get lang identifier.
     *
     * @return int Lang id
     *
     * @deprecated 2.3.0 It's not the good way to get lang identifier
     * @see \Thelia\Model\Customer::getLangId()
     * @see \Thelia\Model\Customer::getLangModel()
     */
    public function getLang()
    {
        return $this->getLangId();
    }

    /**
     * Set lang identifier.
     *
     * @param int $langId Lang identifier
     *
     * @return $this Return $this, allow chaining
     *
     * @deprecated 2.3.0 It's not the good way to set lang identifier
     * @see \Thelia\Model\Customer::setLangId()
     * @see \Thelia\Model\Customer::setLangModel()
     */
    public function setLang($langId)
    {
        return $this->setLangId($langId);
    }

    protected function generateRef()
    {
        $lastCustomer = CustomerQuery::create()
            ->orderById(Criteria::DESC)
            ->findOne()
        ;

        $id = 1;
        if (null !== $lastCustomer) {
            $id = $lastCustomer->getId() + 1;
        }

        return sprintf('CUS%s', str_pad($id, 12, 0, \STR_PAD_LEFT));
    }

    /**
     * @return Address
     */
    public function getDefaultAddress()
    {
        return AddressQuery::create()
            ->filterByCustomer($this)
            ->filterByIsDefault(1)
            ->findOne();
    }

    public function setRef($v)
    {
        if (null !== $v) {
            parent::setRef($v);
        }

        return $this;
    }

    /**
     * create hash for plain password and set it in Customer object.
     *
     * @param string $password plain password before hashing
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return $this|Customer
     */
    public function setPassword($password)
    {
        if ($this->isNew() && ($password === null || trim($password) == '')) {
            throw new InvalidArgumentException('customer password is mandatory on creation');
        }

        if ($password !== null && trim($password) != '') {
            $this->setAlgo('PASSWORD_BCRYPT');

            parent::setPassword(password_hash($password, \PASSWORD_BCRYPT));
        }

        return $this;
    }

    public function erasePassword()
    {
        parent::setPassword(null);

        return $this;
    }

    /*
        public function setRef($ref)
        {
            if (null === $ref && null === $this->ref) {
                parent::setRef($this->generateRef());
            } elseif (null !== $ref) {
                parent::setRef($ref);
            }

            return $this;
        }*/

    public function setEmail($email, $force = false)
    {
        $email = trim($email);

        if (($this->isNew() || $force === true) && ($email === null || $email == '')) {
            throw new InvalidArgumentException('customer email is mandatory');
        }

        if (!$this->isNew() && $force === false) {
            return $this;
        }

        return parent::setEmail($email);
    }

    public function getUsername()
    {
        return $this->getEmail();
    }

    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }

    public function eraseCredentials(): void
    {
        parent::setPassword(null);
        $this->resetModified();
    }

    public function getRoles()
    {
        return [new Role('CUSTOMER')];
    }

    public function getToken()
    {
        return $this->getRememberMeToken();
    }

    public function setToken($token): void
    {
        $this->setRememberMeToken($token)->save();
    }

    public function getSerial()
    {
        return $this->getRememberMeSerial();
    }

    /**
     * @throws PropelException
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getLangModel()->getLocale();
    }

    public function hasOrder()
    {
        $order = OrderQuery::create()
            ->filterByCustomerId($this->getId())
            ->count();

        return $order > 0;
    }

    public function setSerial($serial): void
    {
        $this->setRememberMeSerial($serial)->save();
    }

    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the serial number (for auto-login)
        $this->setRememberMeSerial(uniqid());

        if (null === $this->getRef()) {
            $this->setRef($this->generateRef());
        }

        return true;
    }
}

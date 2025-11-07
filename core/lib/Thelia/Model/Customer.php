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

namespace Thelia\Model;

use Exception;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
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
class Customer extends BaseCustomer implements UserInterface, SecurityUserInterface, PasswordAuthenticatedUserInterface
{
    public string $_validationCodeForEmail;
    public const CODE_LENGTH = 6;

    /**
     * Create or update a customer and its main address. Create a new address if none exists.
     *
     * @param string $plainPassword    customer plain password, hash is made calling setPassword method. Not mandatory parameter but an exception is thrown if customer is new without password
     * @param bool   $forceEmailUpdate true if the email address could be updated
     * @param int    $stateId          customer state id (from State table)
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function createOrUpdate(
        ?int $titleId,
        ?string $firstname,
        ?string $lastname,
        ?string $address1,
        ?string $address2,
        ?string $address3,
        ?string $phone,
        ?string $cellphone,
        ?string $zipcode,
        ?string $city,
        ?int $countryId,
        ?string $email = null,
        ?string $plainPassword = null,
        ?int $lang = null,
        bool $reseller = false,
        $sponsor = null,
        ?float $discount = 0,
        $company = null,
        $ref = null,
        bool $forceEmailUpdate = false,
        ?int $stateId = null,
    ): void {
        $this
            ->setTitleId($titleId)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setEmail($email, $forceEmailUpdate)
            ->setPassword($plainPassword)
            ->setReseller($reseller)
            ->setSponsor($sponsor)
            ->setDiscount($discount ?? 0)
            ->setRef($ref);

        if (null !== $lang) {
            $this->setLangId($lang);
        }

        $con = Propel::getWriteConnection(CustomerTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $address = $this->getDefaultAddress();
            if ($this->isNew()) {
                if (ConfigQuery::isCustomerEmailConfirmationEnable()) {
                    $this->_validationCodeForEmail = $this->setConfirmationTokenWithExpiry();
                }

                $address = (new Address())
                    ->setLabel(Translator::getInstance()->trans('Main address'))
                    ->setIsDefault(1);
                $this->addAddress($address);
            }

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
                ->setStateId($stateId);

            $this->save($con);

            $con->commit();
        } catch (PropelException $propelException) {
            $con->rollBack();

            throw $propelException;
        }
    }

    public function createOrUpdateWithoutAddress(
        int $titleId,
        string $firstname,
        string $lastname,
        string $email,
        string $plainPassword,
        bool $forceEmailUpdate = false,
        ?int $langId = null,
        bool $reseller = false,
        ?string $sponsor = null,
        ?float $discount = null,
        ?string $ref = null,
        bool $enabled = false,
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
            ->setEnable($enabled);

        if (ConfigQuery::isCustomerEmailConfirmationEnable()) {
            $validationCode = $this->setConfirmationTokenWithExpiry();
            $this->_validationCodeForEmail = $validationCode;
        }

        if (null !== $langId) {
            $this->setLangId($langId);
        }

        $this->save();
    }

    /**
     * Return the customer lang, or the default one if none is defined.
     *
     * @return Lang Lang model
     */
    public function getCustomerLang(): Lang
    {
        $lang = $this->getLangModel();

        if (null === $lang) {
            $lang = (new LangQuery())
                ->filterByByDefault(1)
                ->findOne();
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
    public function getLang(): int
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
    public function setLang(int $langId)
    {
        return $this->setLangId($langId);
    }

    protected function generateRef()
    {
        $lastCustomer = CustomerQuery::create()
            ->orderById(Criteria::DESC)
            ->findOne();

        $id = 1;

        if (null !== $lastCustomer) {
            $id = $lastCustomer->getId() + 1;
        }

        return \sprintf('CUS%s', str_pad((string) $id, 12, '0', \STR_PAD_LEFT));
    }

    public function getDefaultAddress(): ?Address
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
     * @return $this|Customer
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setPassword($password)
    {
        if ($this->isNew() && (null === $password || '' === trim($password))) {
            throw new InvalidArgumentException('customer password is mandatory on creation');
        }

        if (null !== $password && '' !== trim($password)) {
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
        $email = trim((string) $email);

        if (($this->isNew() || true === $force) && (null === $email || '' === $email)) {
            throw new InvalidArgumentException('customer email is mandatory');
        }

        if (!$this->isNew() && false === $force) {
            return $this;
        }

        return parent::setEmail($email);
    }

    public function getUsername(): string
    {
        return $this->getEmail();
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->getPassword());
    }

    public function eraseCredentials(): void
    {
        parent::setPassword(null);
        $this->resetModified();
    }

    public function getRoles(): array
    {
        return ['ROLE_CUSTOMER', 'CUSTOMER'];
    }

    public function getToken(): string
    {
        return $this->getRememberMeToken();
    }

    public function setToken($token): void
    {
        $this->setRememberMeToken($token)->save();
    }

    public function getSerial(): string
    {
        return $this->getRememberMeSerial();
    }

    /**
     * @throws PropelException
     */
    public function getLocale(): string
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

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        // Set the serial number (for auto-login)
        $this->setRememberMeSerial(uniqid('', true));

        if (null === $this->getRef()) {
            $this->setRef($this->generateRef());
        }

        return true;
    }

    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }

    public function getId(): int
    {
        return parent::getId() ?? 0;
    }

    public function setConfirmationTokenWithExpiry(int $expirationHours = 24): string
    {
        $codeData = $this->generateValidationCode();

        $this->setConfirmationToken($codeData['hash']);

        $expiryDate = new \DateTime();
        $expiryDate->add(new \DateInterval("PT{$expirationHours}H"));
        $this->setConfirmationTokenExpiresAt($expiryDate);

        return $codeData['code'];
    }

    /**
     * @throws \Exception
     */
    public function verifyActivationCode(string $inputCode): void
    {
        if ($this->isConfirmationTokenExpired()) {
            throw new \Exception('Activation code expired');
        }

        $storedToken = $this->getConfirmationToken();
        if (!$storedToken || !str_contains($storedToken, '.')) {
            throw new \Exception('Activation code error');
        }

        [$hash, $salt] = explode('.', $storedToken, 2);
        $expectedHash = hash('sha256', $inputCode.$salt);

        if (!hash_equals($hash, $expectedHash)) {
            throw new \Exception('Activation code error');
        }
    }

    public function isConfirmationTokenExpired(): bool
    {
        $expiresAt = $this->getConfirmationTokenExpiresAt();

        if (!$expiresAt) {
            return true;
        }

        return new \DateTime() > $expiresAt;
    }

    public function clearConfirmationToken(): void
    {
        $this->setConfirmationToken(null);
        $this->setConfirmationTokenExpiresAt(null);
    }

    /***
     * @return array ['code' => string, 'hash' => string]
     */
    private function generateValidationCode(): array
    {
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', \STR_PAD_LEFT);

        $salt = bin2hex(random_bytes(16));
        $hash = hash('sha256', $code.$salt);

        $tokenForDb = $hash.'.'.$salt;

        return [
            'code' => $code,
            'hash' => $tokenForDb,
        ];
    }
}

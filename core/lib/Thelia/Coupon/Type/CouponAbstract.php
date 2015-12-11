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

namespace Thelia\Coupon\Type;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\ConditionOrganizerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Form\CouponCreationForm;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;

/**
 * Assist in writing a CouponInterface
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class CouponAbstract implements CouponInterface
{
    /**
     * The dataset name for all coupon specific input fields, that do not appear in the CouPonCreationForm form.
      *
     * In the input form, these fields have to be created like:
      *
     *    thelia_coupon_specific[my_field, thelia_coupon_creation_extended[my_other_field]
      *
     * use the makeCouponField() method to do that safely.
     */
    const COUPON_DATASET_NAME = 'coupon_specific';

    /**
     * A standard 'amount' filed name, thant can be used in coupons which extends this class
     */
    const AMOUNT_FIELD_NAME = 'amount';

    /** @var  FacadeInterface Provide necessary value from Thelia */
    protected $facade = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /** @var ConditionOrganizerInterface  */
    protected $organizer = null;

    /** @var ConditionCollection Array of ConditionInterface */
    protected $conditions = null;

    /** @var ConditionEvaluator Condition validator */
    protected $conditionEvaluator = null;

    /** @var string Service Id  */
    protected $serviceId = null;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect)  */
    protected $amount = 0;

    /** @var array Get the Coupon effects params */
    protected $effects = array('amount' => 0);

    /** @var string Coupon code (ex: XMAS) */
    protected $code = null;

    /** @var string Coupon title (ex: Coupon for XMAS) */
    protected $title = null;

    /** @var string Coupon short description */
    protected $shortDescription = null;

    /** @var string Coupon description */
    protected $description = null;

    /** @var bool if Coupon is enabled */
    protected $isEnabled = false;

    /** @var \DateTime Coupon start date */
    protected $startDate = null;

    /** @var \DateTime Coupon expiration date */
    protected $expirationDate = null;

    /** @var bool if Coupon is cumulative */
    protected $isCumulative = false;

    /** @var bool if Coupon is removing postage */
    protected $isRemovingPostage = false;

    /** @var int Max time a Coupon can be used (-1 = unlimited) */
    protected $maxUsage = -1;

    /** @var bool if Coupon is available for Products already on special offers */
    protected $isAvailableOnSpecialOffers = false;

    /** @var CouponCountry[] list of country IDs for which shipping is free. All if empty*/
    protected $freeShippingForCountries = [];

    /** @var CouponModule[] list of shipping module IDs for which shippiog is free. All if empty*/
    protected $freeShippingForModules = [];

    /** @var true if usage count is per customer only */
    protected $perCustomerUsageCount;

    /**
     * Constructor
     *
     * @param FacadeInterface $facade Service facade
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->facade = $facade;
        $this->translator = $facade->getTranslator();
        $this->conditionEvaluator = $facade->getConditionEvaluator();
    }

    /**
     * Set Condition Organizer
     *
     * @param ConditionOrganizerInterface $organizer Manage Condition groups (&& and ||)
     *
     * @return $this
     */
    public function setOrganizer($organizer)
    {
        $this->organizer = $organizer;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount
    ) {
        $this->code = $code;
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->description = $description;

        $this->isCumulative = $isCumulative;
        $this->isRemovingPostage = $isRemovingPostage;

        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
        $this->isEnabled = $isEnabled;
        $this->maxUsage = $maxUsage;
        $this->expirationDate = $expirationDate;
        $this->facade = $facade;

        $this->effects = $effects;
        // Amount is now optional.
        $this->amount = isset($effects[self::AMOUNT_FIELD_NAME]) ? $effects[self::AMOUNT_FIELD_NAME] : 0;

        $this->freeShippingForCountries = $freeShippingForCountries;
        $this->freeShippingForModules = $freeShippingForModules;
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    /**
     * @param  true  $perCustomerUsageCount
     * @return $this
     */
    public function setPerCustomerUsageCount($perCustomerUsageCount)
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    /**
     * @return true
     */
    public function getPerCustomerUsageCount()
    {
        return $this->perCustomerUsageCount;
    }

    /**
     * Return Coupon code (ex: XMAS)
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Return Coupon title (ex: Coupon for XMAS)
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return Coupon short description
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * Return Coupon description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given
     *
     * @return bool
     */
    public function isCumulative()
    {
        return $this->isCumulative;
    }

    /**
     * If Coupon is removing Checkout Postage
     *
     * @return bool
     */
    public function isRemovingPostage()
    {
        return $this->isRemovingPostage;
    }

    /**
     * @return array list of country IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForCountries()
    {
        return $this->freeShippingForCountries;
    }

    /**
     * @return array list of module IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForModules()
    {
        return $this->freeShippingForModules;
    }

    /**
     * @inheritdoc
     */
    public function exec()
    {
        return $this->amount;
    }

    /**
     * Return condition to validate the Coupon or not
     *
     * @return ConditionCollection
     */
    public function getConditions()
    {
        return clone $this->conditions;
    }

    /**
     * Replace the existing Conditions by those given in parameter
     * If one Condition is badly implemented, no Condition will be added
     *
     * @param ConditionCollection $conditions ConditionInterface to add
     *
     * @return $this
     * @throws \Thelia\Exception\InvalidConditionException
     */
    public function setConditions(ConditionCollection $conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Return Coupon expiration date
     *
     * @return \DateTime
     */
    public function getExpirationDate()
    {
        return clone $this->expirationDate;
    }

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price
     *
     * @return boolean
     */
    public function isAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Check if Coupon has been disabled by admin
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited
     *
     * @return int
     */
    public function getMaxUsage()
    {
        return $this->maxUsage;
    }

    /**
     * Check if the Coupon is already Expired
     *
     * @return bool
     */
    public function isExpired()
    {
        $ret = true;

        $now = new \DateTime();
        if ($this->expirationDate > $now) {
            $ret = false;
        }

        return $ret;
    }

    /**
     * Get Coupon Manager service Id
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Check if the current state of the application is matching this Coupon conditions
     * Thelia variables are given by the FacadeInterface
     *
     * @return bool
     */
    public function isMatching()
    {
        return $this->conditionEvaluator->isMatching($this->conditions);
    }

    /**
     * This is the field label than will be displayed in the form.
     * This method should be overridden to be useful.
     *
     * For backward compatibility only.
     *
     * @return string
     */
    public function getInputName()
    {
        return "Please override getInputName() method";
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     * Override this method to do something useful
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs()
    {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x.html', [
                'label'     => $this->getInputName(),
                'fieldId'   => self::AMOUNT_FIELD_NAME,
                'fieldName' => $this->makeCouponFieldName(self::AMOUNT_FIELD_NAME),
                'value'     => $this->amount
            ]);
    }

    /**
     * This methods checks a field value. If the field has a correct value, this value is returned
     * Otherwise, an InvalidArgumentException describing the problem should be thrown.
     *
     * This method should be overriden to be useful.
     *
     * @param  string                    $fieldName
     * @param  string                    $fieldValue
     * @return mixed
     * @throws \InvalidArgumentException if the field value is not valid.
     */
    protected function checkCouponFieldValue(/** @noinspection PhpUnusedParameterInspection */ $fieldName, $fieldValue)
    {
        return $fieldValue;
    }

    /**
     * A helper to get the value of a standard field name
     *
     * @param string $fieldName    the field name
     * @param array  $data         the input form data (e.g. $form->getData())
     * @param mixed  $defaultValue the default value if the field is not found.
     *
     * @return mixed the input value, or the default one
     *
     * @throws \InvalidArgumentException if the field is not found, and no default value has been defined.
     */
    protected function getCouponFieldValue($fieldName, $data, $defaultValue = null)
    {
        if (isset($data[self::COUPON_DATASET_NAME][$fieldName])) {
            return $this->checkCouponFieldValue(
                $fieldName,
                $data[self::COUPON_DATASET_NAME][$fieldName]
            );
        } elseif (null !== $defaultValue) {
            return $defaultValue;
        } else {
            throw new \InvalidArgumentException(sprintf("The coupon field name %s was not found in the coupon form", $fieldName));
        }
    }

    /**
     * A helper to create an standard field name that will be used in the coupon form
     *
     * @param  string $fieldName the field name
     * @return string the complete name, ready to be used in a form.
     */
    protected function makeCouponFieldName($fieldName)
    {
        return sprintf("%s[%s][%s]", CouponCreationForm::COUPON_CREATION_FORM_NAME, self::COUPON_DATASET_NAME, $fieldName);
    }

    /**
     * Return a list of the fields name for this coupon.
     *
     * @return array
     */
    protected function getFieldList()
    {
        return [self::AMOUNT_FIELD_NAME];
    }

    /**
     * Create the effect array from the list of fields
     *
     * @param  array $data the input form data (e.g. $form->getData())
     * @return array a filedName => fieldValue array
     */
    public function getEffects($data)
    {
        $effects = [];

        foreach ($this->getFieldList() as $fieldName) {
            $effects[$fieldName] = $this->getCouponFieldValue($fieldName, $data);
        }

        return $effects;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        // Does nothing. Override this function as needed.
    }

    public function isInUse()
    {
        return in_array(
            $this->code,
            $this->facade->getRequest()->getSession()->getConsumedCoupons()
        );
    }
}

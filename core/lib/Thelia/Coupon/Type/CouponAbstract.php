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

use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\FacadeInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionOrganizerInterface;

/**
 * Assist in writing a CouponInterface
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class CouponAbstract implements CouponInterface
{
    const INPUT_EXTENDED__NAME = 'thelia_coupon_creation_extended';

    const INPUT_AMOUNT_NAME = 'amount';

    /** @var array Extended Inputs to manage */
    protected $extendedInputs = array();

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
     * Set Coupon
     *
     * @param FacadeInterface $facade                     Provides necessary value from Thelia
     * @param string          $code                       Coupon code (ex: XMAS)
     * @param string          $title                      Coupon title (ex: Coupon for XMAS)
     * @param string          $shortDescription           Coupon short description
     * @param string          $description                Coupon description
     * @param array           $effects                    Coupon effects params
     * @param bool            $isCumulative               If Coupon is cumulative
     * @param bool            $isRemovingPostage          If Coupon is removing postage
     * @param bool            $isAvailableOnSpecialOffers If available on Product already
     *                                                    on special offer price
     * @param bool            $isEnabled                  False if Coupon is disabled by admin
     * @param int             $maxUsage                   How many usage left
     * @param \Datetime       $expirationDate             When the Code is expiring
     *
     * @return $this
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
        \DateTime $expirationDate
    )
    {
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
        $this->amount = $effects[self::INPUT_AMOUNT_NAME];

        return $this;
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
     * Return effects generated by the coupon
     * A negative value
     *
     * @return float Amount removed from the Total Checkout
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
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     * Override this method to do something useful
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs() {
        return $this->facade->getParser()->render('coupon/type-fragments/remove-x-amount.html', [
                'label'     => $this->getInputName(),
                'fieldName' => self::INPUT_AMOUNT_NAME,
                'value'     => $this->amount
            ]);
    }

    /**
     * Get all extended inputs name to manage
     *
     * @return mixed
     */
    public function getExtendedInputs()
    {
        return $this->extendedInputs;
    }

}

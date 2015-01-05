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


namespace Thelia\Model;

/**
 * Class OrderPostage
 * @package Thelia\Model
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OrderPostage
{
    /** @var float */
    protected $amount;

    /** @var float */
    protected $amountTax;

    /** @var string */
    protected $taxRuleTitle;

    public function __construct($amount = 0.0, $amountTax = 0.0, $taxRuleTitle = '')
    {
        $this->amount = $amount;
        $this->amountTax = $amountTax;
        $this->taxRuleTitle = $taxRuleTitle;
    }

    /**
     * Convert a amount or OrderPostage object to an OrderPostage object
     *
     * @param OrderPostage|float $postage
     * @return OrderPostage
     */
    public static function loadFromPostage($postage)
    {
        if ($postage instanceof OrderPostage) {
            $orderPostage = $postage;
        } else {
            $orderPostage = new OrderPostage($postage);
        }

        return $orderPostage;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return float
     */
    public function getAmountTax()
    {
        return $this->amountTax;
    }

    /**
     * @param float $amountTax
     */
    public function setAmountTax($amountTax)
    {
        $this->amountTax = $amountTax;
    }

    /**
     * @return string
     */
    public function getTaxRuleTitle()
    {
        return $this->taxRuleTitle;
    }

    /**
     * @param string $taxRuleTitle
     */
    public function setTaxRuleTitle($taxRuleTitle)
    {
        $this->taxRuleTitle = $taxRuleTitle;
    }
}

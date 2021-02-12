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

/**
 * Class OrderPostage.
 *
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
     * Convert a amount or OrderPostage object to an OrderPostage object.
     *
     * @param OrderPostage|float $postage
     *
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
    public function setAmount($amount): void
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
    public function setAmountTax($amountTax): void
    {
        // We have to round the postage tax to prevent small delta amounts in tax calculations.
        $this->amountTax = round($amountTax, 2);
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
    public function setTaxRuleTitle($taxRuleTitle): void
    {
        $this->taxRuleTitle = $taxRuleTitle;
    }
}

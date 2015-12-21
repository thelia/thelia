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

namespace Thelia\Core\Event\Product;

class ProductCreateEvent extends ProductEvent
{
    protected $ref;
    protected $title;
    protected $locale;
    protected $default_category;
    protected $visible;
    protected $virtual;

    protected $basePrice;
    protected $baseWeight;
    protected $taxRuleId;
    protected $currencyId;
    protected $baseQuantity;
    protected $templateId;

    public function getRef()
    {
        return $this->ref;
    }

    public function setRef($ref)
    {
        $this->ref = $ref;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDefaultCategory()
    {
        return $this->default_category;
    }

    public function setDefaultCategory($default_category)
    {
        $this->default_category = $default_category;

        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
    }

    public function setVirtual($virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }

    public function getVirtual()
    {
        return $this->virtual;
    }

    public function getBasePrice()
    {
        return $this->basePrice;
    }

    public function setBasePrice($basePrice)
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getBaseWeight()
    {
        return $this->baseWeight;
    }

    public function setBaseWeight($baseWeight)
    {
        $this->baseWeight = $baseWeight;

        return $this;
    }

    public function getTaxRuleId()
    {
        return $this->taxRuleId;
    }

    public function setTaxRuleId($taxRuleId)
    {
        $this->taxRuleId = $taxRuleId;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currencyId;
    }

    public function setCurrencyId($currencyId)
    {
        $this->currencyId = $currencyId;

        return $this;
    }

    /**
     * This method is an alias of setBasePrice and used by the event when binding a form
     *
     * @param float $price price for this new product
     * @return $this
     * @see setBasePrice
     */
    public function setPrice($price)
    {
        return $this->setBasePrice($price);
    }

    /**
     * This method is an alias of setBaseWeight and used by the event when binding a form
     *
     * @param float $weight
     * @return $this
     * @see setBaseWeight
     */
    public function setWeight($weight)
    {
        return $this->setBaseWeight($weight);
    }

    /**
     * This method is an alias of setCurrencyId and used by the event when binding a form
     *
     * @param $currencyId
     * @return $this
     * @see setCurrencyId
     */
    public function setCurrency($currencyId)
    {
        return $this->setCurrencyId($currencyId);
    }

    /**
     * This method is an alias of setTaxRuleId and used by the event when binding a form
     *
     * @param $taxRuleId
     * @return $this
     * @see setTaxRuleId
     */
    public function setTaxRule($taxRuleId)
    {
        return $this->setTaxRuleId($taxRuleId);
    }

    /**
     * @return integer
     */
    public function getBaseQuantity()
    {
        return $this->baseQuantity;
    }

    /**
     * @param integer $baseQuantity
     * @return $this
     */
    public function setBaseQuantity($baseQuantity)
    {
        $this->baseQuantity = $baseQuantity;
        return $this;
    }

    /**
     * This method is an alias of setBaseQuantity and used by the event when binding a form
     *
     * @param integer $quantity quantity for this new product
     * @return $this
     * @see setBaseQuantity
     */
    public function setQuantity($quantity)
    {
        return $this->setBaseQuantity($quantity);
    }

    /**
     * @return integer
     */
    public function getTemplateId()
    {
        return $this->templateId;
    }

    /**
     * @param integer $templateId
     * @return $this
     */
    public function setTemplateId($templateId)
    {
        $this->templateId = $templateId;
        return $this;
    }
}

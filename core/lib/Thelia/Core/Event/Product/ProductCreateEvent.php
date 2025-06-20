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

    public function setRef($ref): static
    {
        $this->ref = $ref;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDefaultCategory()
    {
        return $this->default_category;
    }

    public function setDefaultCategory($default_category): static
    {
        $this->default_category = $default_category;

        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function setVirtual($virtual): static
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

    public function setBasePrice($basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getBaseWeight()
    {
        return $this->baseWeight;
    }

    public function setBaseWeight($baseWeight): static
    {
        $this->baseWeight = $baseWeight;

        return $this;
    }

    public function getTaxRuleId()
    {
        return $this->taxRuleId;
    }

    public function setTaxRuleId($taxRuleId): static
    {
        $this->taxRuleId = $taxRuleId;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currencyId;
    }

    public function setCurrencyId($currencyId): static
    {
        $this->currencyId = $currencyId;

        return $this;
    }

    /**
     * This method is an alias of setBasePrice and used by the event when binding a form.
     *
     * @param float $price price for this new product
     *
     * @return $this
     *
     * @see setBasePrice
     */
    public function setPrice($price): static
    {
        return $this->setBasePrice($price);
    }

    /**
     * This method is an alias of setBaseWeight and used by the event when binding a form.
     *
     * @param float $weight
     *
     * @return $this
     *
     * @see setBaseWeight
     */
    public function setWeight($weight): static
    {
        return $this->setBaseWeight($weight);
    }

    /**
     * This method is an alias of setCurrencyId and used by the event when binding a form.
     *
     * @return $this
     *
     * @see setCurrencyId
     */
    public function setCurrency($currencyId): static
    {
        return $this->setCurrencyId($currencyId);
    }

    /**
     * This method is an alias of setTaxRuleId and used by the event when binding a form.
     *
     * @return $this
     *
     * @see setTaxRuleId
     */
    public function setTaxRule($taxRuleId): static
    {
        return $this->setTaxRuleId($taxRuleId);
    }

    /**
     * @return int
     */
    public function getBaseQuantity()
    {
        return $this->baseQuantity;
    }

    /**
     * @param int $baseQuantity
     *
     * @return $this
     */
    public function setBaseQuantity($baseQuantity): static
    {
        $this->baseQuantity = $baseQuantity;

        return $this;
    }

    /**
     * This method is an alias of setBaseQuantity and used by the event when binding a form.
     *
     * @param int $quantity quantity for this new product
     *
     * @return $this
     *
     * @see setBaseQuantity
     */
    public function setQuantity($quantity): static
    {
        return $this->setBaseQuantity($quantity);
    }

    /**
     * @return int
     */
    public function getTemplateId()
    {
        return $this->templateId;
    }

    /**
     * @param int $templateId
     *
     * @return $this
     */
    public function setTemplateId($templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }
}

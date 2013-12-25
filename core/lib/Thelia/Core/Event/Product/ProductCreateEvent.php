<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Product;

class ProductCreateEvent extends ProductEvent
{
    protected $ref;
    protected $title;
    protected $locale;
    protected $default_category;
    protected $visible;

    protected $basePrice;
    protected $baseWeight;
    protected $taxRuleId;
    protected $currencyId;

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
}

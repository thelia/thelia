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

namespace Thelia\Core\Event\Tax;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\TaxRule;

class TaxRuleEvent extends ActionEvent
{
    protected $taxRule = null;

    protected $locale;
    protected $id;
    protected $title;
    protected $description;
    protected $countryList;
    protected $countryDeletedList;
    protected $taxList;

    public function __construct(TaxRule $taxRule = null)
    {
        $this->taxRule = $taxRule;
    }

    public function hasTaxRule()
    {
        return ! is_null($this->taxRule);
    }

    public function getTaxRule()
    {
        return $this->taxRule;
    }

    public function setTaxRule(TaxRule $taxRule)
    {
        $this->taxRule = $taxRule;

        return $this;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setCountryList($countryList)
    {
        $this->countryList = $countryList;

        return $this;
    }

    public function getCountryList()
    {
        return $this->countryList;
    }

    public function getCountryDeletedList()
    {
        return $this->countryDeletedList;
    }

    public function setCountryDeletedList($countryDeletedList)
    {
        $this->countryDeletedList = $countryDeletedList;
        return $this;
    }

    public function setTaxList($taxList)
    {
        $this->taxList = $taxList;

        return $this;
    }

    public function getTaxList()
    {
        return $this->taxList;
    }
}

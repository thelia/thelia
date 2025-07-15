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

namespace Thelia\Core\Event\Tax;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\TaxRule;

class TaxRuleEvent extends ActionEvent
{
    protected $locale;
    protected $id;
    protected $title;
    protected $description;
    protected $countryList;
    protected $countryDeletedList;
    protected $taxList;

    public function __construct(protected ?TaxRule $taxRule = null)
    {
    }

    public function hasTaxRule(): bool
    {
        return $this->taxRule instanceof TaxRule;
    }

    public function getTaxRule(): ?TaxRule
    {
        return $this->taxRule;
    }

    public function setTaxRule(TaxRule $taxRule): static
    {
        $this->taxRule = $taxRule;

        return $this;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setCountryList($countryList): static
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

    public function setCountryDeletedList($countryDeletedList): static
    {
        $this->countryDeletedList = $countryDeletedList;

        return $this;
    }

    public function setTaxList($taxList): static
    {
        $this->taxList = $taxList;

        return $this;
    }

    public function getTaxList()
    {
        return $this->taxList;
    }
}

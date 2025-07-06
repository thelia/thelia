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
use Thelia\Model\Tax;
use Thelia\TaxEngine\TaxTypeInterface;

class TaxEvent extends ActionEvent
{
    protected $locale;

    protected $id;

    protected $title;

    protected $description;

    protected $type;

    protected $requirements;

    protected ?TaxTypeInterface $taxTypeService = null;

    public function __construct(protected ?Tax $tax = null)
    {
    }

    public function hasTax(): bool
    {
        return $this->tax instanceof Tax;
    }

    public function getTax(): ?Tax
    {
        return $this->tax;
    }

    public function setTax(Tax $tax): static
    {
        $this->tax = $tax;

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

    public function setType($type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setRequirements($requirements): static
    {
        $this->requirements = $requirements;

        return $this;
    }

    public function getRequirements()
    {
        return $this->requirements;
    }

    public function getTaxTypeService(): ?TaxTypeInterface
    {
        return $this->taxTypeService;
    }

    public function setTaxTypeService(TaxTypeInterface $taxTypeService): static
    {
        $this->taxTypeService = $taxTypeService;

        return $this;
    }
}

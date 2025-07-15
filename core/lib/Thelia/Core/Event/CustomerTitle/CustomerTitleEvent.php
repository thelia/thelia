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

namespace Thelia\Core\Event\CustomerTitle;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CustomerTitle;

/**
 * Class CustomerTitleEvent.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerTitleEvent extends ActionEvent
{
    protected bool $default = false;
    protected string $short;
    protected string $long;
    protected string $locale;
    protected ?CustomerTitle $customerTitle = null;

    public function getCustomerTitle(): CustomerTitle
    {
        return $this->customerTitle;
    }

    /**
     * @return $this
     */
    public function setCustomerTitle(?CustomerTitle $customerTitle = null): static
    {
        $this->customerTitle = $customerTitle;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * @return $this
     */
    public function setDefault(bool $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLong(): string
    {
        return $this->long;
    }

    /**
     * @return $this
     */
    public function setLong(string $long): static
    {
        $this->long = $long;

        return $this;
    }

    public function getShort(): string
    {
        return $this->short;
    }

    /**
     * @return $this
     */
    public function setShort(string $short): static
    {
        $this->short = $short;

        return $this;
    }
}

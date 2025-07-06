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

namespace Thelia\Core\Event\Currency;

class CurrencyUpdateEvent extends CurrencyCreateEvent
{
    /** @var int */
    protected $is_default;

    /** @var int */
    protected $visible;

    /**
     * @param int $currencyId
     */
    public function __construct($currencyId)
    {
        $this->setCurrencyId($currencyId);
    }

    /**
     * @return int
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * @return $this
     */
    public function setIsDefault($is_default): static
    {
        $this->is_default = $is_default;

        return $this;
    }

    /**
     * @return int
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * @return $this
     */
    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }
}

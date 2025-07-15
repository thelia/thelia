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
    protected int $is_default;
    protected int $visible;

    public function __construct(int $currencyId)
    {
        $this->setCurrencyId($currencyId);
    }

    public function getIsDefault(): int
    {
        return $this->is_default;
    }

    /**
     * @return $this
     */
    public function setIsDefault(int $is_default): static
    {
        $this->is_default = $is_default;

        return $this;
    }

    public function getVisible(): int
    {
        return $this->visible;
    }

    /**
     * @return $this
     */
    public function setVisible(int $visible): static
    {
        $this->visible = $visible;

        return $this;
    }
}

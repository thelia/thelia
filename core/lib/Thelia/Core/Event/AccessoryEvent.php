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

namespace Thelia\Core\Event;

use Thelia\Model\Accessory;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\AccessoryEvent
 */
class AccessoryEvent extends ActionEvent
{
    public function __construct(public ?Accessory $accessory = null)
    {
    }

    public function hasAccessory(): bool
    {
        return $this->accessory instanceof Accessory;
    }

    public function getAccessory(): ?Accessory
    {
        return $this->accessory;
    }

    public function setAccessory(Accessory $accessory): static
    {
        $this->accessory = $accessory;

        return $this;
    }
}

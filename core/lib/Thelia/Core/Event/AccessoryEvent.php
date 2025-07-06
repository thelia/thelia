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
    /**
     * @var Accessory|null
     */
    public $accessory;

    public function __construct(Accessory $accessory = null)
    {
        $this->accessory = $accessory;
    }

    public function hasAccessory(): bool
    {
        return null !== $this->accessory;
    }

    public function getAccessory()
    {
        return $this->accessory;
    }

    public function setAccessory(Accessory $accessory): static
    {
        $this->accessory = $accessory;

        return $this;
    }
}

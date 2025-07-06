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
namespace Thelia\Form\ShippingZone;

/**
 * Class ShippingZoneRemoveArea.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZoneRemoveArea extends ShippingZoneAddArea
{
    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_shippingzone_remove_area';
    }
}

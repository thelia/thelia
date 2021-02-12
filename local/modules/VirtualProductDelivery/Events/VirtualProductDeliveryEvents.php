<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VirtualProductDelivery\Events;

use Thelia\Core\Event\ActionEvent;

/**
 * Class VirtualProductDeliveryEvents
 * @package VirtualProductDelivery\Events
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductDeliveryEvents extends ActionEvent
{
    public const ORDER_VIRTUAL_FILES_AVAILABLE = 'virtual_product_delivery.virtual_files_available';
}

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


namespace VirtualProductDelivery\Events;

use Thelia\Core\Event\ActionEvent;

/**
 * Class VirtualProductDeliveryEvents
 * @package VirtualProductDelivery\Events
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductDeliveryEvents extends ActionEvent
{
    const ORDER_VIRTUAL_FILES_AVAILABLE = 'virtual_product_delivery.virtual_files_available';
}

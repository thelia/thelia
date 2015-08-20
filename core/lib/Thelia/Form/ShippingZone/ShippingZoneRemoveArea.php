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

namespace Thelia\Form\ShippingZone;

/**
 * Class ShippingZoneRemoveArea
 * @package Thelia\Form\ShippingZone
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZoneRemoveArea extends ShippingZoneAddArea
{
    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_shippingzone_remove_area';
    }
}

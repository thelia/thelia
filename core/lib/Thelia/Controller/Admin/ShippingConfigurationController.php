<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

/**
 * Class ShippingConfigurationController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ShippingConfigurationController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth("admin.shipping-configuration.view")) return $response;
        return $this->render("shipping-configuration", array("display_shipping_configuration" => 20));
    }
    
    public function updateAction($shipping_configuration_id)
    {

        return $this->render("shipping-configuration-edit", array(
            "shipping_configuration_id" => $shipping_configuration_id
        ));
    }
}
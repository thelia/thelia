<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Tools\URL;
use Thelia\Core\Security\SecurityContext;

/**
 * This class implements variour admin template utilities
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AdminUtilities extends AbstractSmartyPlugin
{
    private $securityContext;

    public function __construct(SecurityContext $securityContext)
    {
        $this->securityContext = $securityContext;
    }

    public function generatePositionChangeBlock($params, &$smarty)
    {
        // The required permissions
        $permission = $this->getParam($params, 'permission');

        // The base position change path
        $path = $this->getParam($params, 'path');

        // The URL parameter the object ID is assigned
        $url_parameter = $this->getParam($params, 'url_parameter');

        // The current object position
        $position = $this->getParam($params, 'position');

        // The object ID
        $id = $this->getParam($params, 'id');

        // The in place dition class
        $in_place_edit_class = $this->getParam($params, 'in_place_edit_class');

        /*
         <a href="{url path='/admin/configuration/currencies/positionUp' currency_id=$ID}"><i class="icon-arrow-up"></i></a>
        <span class="currencyPositionChange" data-id="{$ID}">{$POSITION}</span>
        <a href="{url path='/admin/configuration/currencies/positionDown' currency_id=$ID}"><i class="icon-arrow-down"></i></a>
        */

        if ($permissions == null || $this->securityContext->isGranted("ADMIN", array($permission))) {
            return sprintf(
                '<a href="%s"><i class="glyphicon glyphicon-arrow-up"></i></a><span class="%s" data-id="%s">%s</span><a href="%s"><i class="glyphicon glyphicon-arrow-down"></i></a>',
                URL::getInstance()->absoluteUrl($path, array('mode' => 'up', $url_parameter => $id)),
                $in_place_edit_class,
                $id,
                $position,
                URL::getInstance()->absoluteUrl($path, array('mode' => 'down', $url_parameter => $id))
            );
        } else {
            return $position;
        }
    }

    /**
     * Generates the link of a sortable column header
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  no text is returned.
     */
    public function generateSortableColumnHeader($params, &$smarty)
    {
        // The current order of the table
        $current_order = $this->getParam($params, 'current_order');

        // The column ascending order
        $order = $this->getParam($params, 'order');

        // The column descending order label
        $reverse_order = $this->getParam($params, 'reverse_order');

        // The order change path
        $path = $this->getParam($params, 'path');

        // The column label
        $label = $this->getParam($params, 'label');

        // The request parameter
        $request_parameter_name = $this->getParam($params, 'request_parameter_name', 'order');

        if ($current_order == $order) {
            $icon = 'up';
            $order_change = $reverse_order;
        } elseif ($current_order == $reverse_order) {
            $icon = 'down';
            $order_change = $order;
        } else {
            $order_change = $order;
        }

        if (! empty($icon))
            $output = sprintf('<i class="glyphicon glyphicon-chevron-%s"></i> ', $icon);
        else
            $output = '';

        return sprintf('%s<a href="%s">%s</a>', $output, URL::getInstance()->absoluteUrl($path, array($request_parameter_name => $order_change)), $label);
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
                new SmartyPluginDescriptor('function', 'admin_sortable_header', $this, 'generateSortableColumnHeader'),
                new SmartyPluginDescriptor('function', 'admin_position_block' , $this, 'generatePositionChangeBlock'),
        );
    }
}

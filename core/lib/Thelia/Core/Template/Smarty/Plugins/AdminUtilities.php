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
use Thelia\Core\Template\TemplateHelper;

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

    protected function fetch_snippet($smarty, $templateName, $variablesArray)
    {
        $data = '';

        $snippet_path = sprintf('%s/%s/%s.html',
                THELIA_TEMPLATE_DIR,
                TemplateHelper::getInstance()->getActiveAdminTemplate()->getPath(),
                $templateName
        );

        if (false !== $snippet_content = file_get_contents($snippet_path)) {

            $smarty->assign($variablesArray);

            $data = $smarty->fetch(sprintf('string:%s', $snippet_content));
        }

        return $data;
    }

    public function generatePositionChangeBlock($params, &$smarty)
    {
        // The required permissions
        $resource = $this->getParam($params, 'resource');
        $module = $this->getParam($params, 'module');
        $access = $this->getParam($params, 'access');

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

        if ($permissions == null || $this->securityContext->isGranted(
                "ADMIN",
                $resource === null ? array() : array($resource),
                $module === null ? array() : array($module),
                array($access))
        ) {
            return $this->fetch_snippet($smarty, 'includes/admin-utilities-position-block', array(
                    'admin_utilities_go_up_url'           => URL::getInstance()->absoluteUrl($path, array('mode' => 'up', $url_parameter => $id)),
                    'admin_utilities_in_place_edit_class' => $in_place_edit_class,
                    'admin_utilities_object_id'           => $id,
                    'admin_utilities_current_position'    => $position,
                    'admin_utilities_go_down_url'         => URL::getInstance()->absoluteUrl($path, array('mode' => 'down', $url_parameter => $id))
            ));
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
            $sort_direction = 'up';
            $order_change = $reverse_order;
        } elseif ($current_order == $reverse_order) {
            $sort_direction = 'down';
            $order_change = $order;
        } else {
            $order_change = $order;
        }

        return $this->fetch_snippet($smarty, 'includes/admin-utilities-sortable-column-header', array(
                'admin_utilities_sort_direction' => $sort_direction,
                'admin_utilities_sorting_url'    => URL::getInstance()->absoluteUrl($path, array($request_parameter_name => $order_change)),
                'admin_utilities_header_text'    => $label
        ));
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

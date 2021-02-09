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

namespace TheliaSmarty\Template\Plugins;

use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Tools\URL;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * This class implements variour admin template utilities
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AdminUtilities extends AbstractSmartyPlugin
{
    private $securityContext;
    private $templateHelper;

    public function __construct(SecurityContext $securityContext, TemplateHelperInterface $templateHelper)
    {
        $this->securityContext = $securityContext;
        $this->templateHelper = $templateHelper;
    }

    /**
     * @param \Smarty $smarty
     * @param string $templateName
     * @param array $variablesArray
     * @return string
     * @throws \Exception
     * @throws \SmartyException
     */
    protected function fetchSnippet($smarty, $templateName, $variablesArray)
    {
        $snippet_content = file_get_contents(
            $this->templateHelper->getActiveAdminTemplate()->getTemplateFilePath(
                $templateName . '.html'
            )
        );

        $smarty->assign($variablesArray);

        $data = $smarty->fetch(sprintf('string:%s', $snippet_content));

        return $data;
    }

    public function optionOffsetGenerator($params, &$smarty)
    {
        $label = $this->getParam($params, 'label', null);

        if (null !== $level = $this->getParam($params, [ 'l', 'level'], null)) {
            $label = str_repeat('&nbsp;', 4 * $level) . $label;
        }

        return $label;
    }

    /**
     * @param $params
     * @param $smarty
     * @return mixed|string
     * @throws \Exception
     * @throws \SmartyException
     */
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

        if ($this->securityContext->isGranted(
            ["ADMIN"],
            $resource === null ? [] : [$resource],
            $module === null ? [] : [$module],
            [$access]
        )
        ) {
            return $this->fetchSnippet($smarty, 'includes/admin-utilities-position-block', [
                    'admin_utilities_go_up_url'           => URL::getInstance()->absoluteUrl($path, ['mode' => 'up', $url_parameter => $id]),
                    'admin_utilities_in_place_edit_class' => $in_place_edit_class,
                    'admin_utilities_object_id'           => $id,
                    'admin_utilities_current_position'    => $position,
                    'admin_utilities_go_down_url'         => URL::getInstance()->absoluteUrl($path, ['mode' => 'down', $url_parameter => $id])
            ]);
        }  
            return $position;
    }

    /**
     * Generates the link of a sortable column header
     *
     * @param  array   $params
     * @param  \Smarty $smarty
     * @return string  no text is returned.
     * @throws \Exception
     * @throws \SmartyException
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

        return $this->fetchSnippet($smarty, 'includes/admin-utilities-sortable-column-header', [
                'admin_utilities_sort_direction' => $sort_direction,
                'admin_utilities_sorting_url'    => URL::getInstance()->absoluteUrl($path, [$request_parameter_name => $order_change]),
                'admin_utilities_header_text'    => $label
        ]);
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'admin_sortable_header', $this, 'generateSortableColumnHeader'),
            new SmartyPluginDescriptor('function', 'admin_position_block', $this, 'generatePositionChangeBlock'),
            new SmartyPluginDescriptor('function', 'option_offset', $this, 'optionOffsetGenerator'),
        ];
    }
}

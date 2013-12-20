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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Model\ModuleQuery;

class Module extends AbstractSmartyPlugin
{
    /**
     * @var bool application debug mode
     */
    protected $debug;

    /**
     * @var Request $request
     */
    protected $request;

    public function __construct($debug, Request $request)
    {
        $this->debug = $debug;
        $this->request = $request;
    }
    /**
     * Process theliaModule template inclusion function
     *
     * @param  unknown $params
     * @param \Smarty_Internal_Template $template
     * @internal param \Thelia\Core\Template\Smarty\Plugins\unknown $smarty
     *
     * @return string
     */
    public function theliaModule($params, \Smarty_Internal_Template $template)
    {
        $content = null;

        if (false !== $location = $this->getParam($params, 'location', false)) {

            if($this->debug === true && $this->request->get('SHOW_INCLUDE')) {
                echo sprintf('<div style="background-color: #C82D26; border-color: #000000; border: solid;">%s</div>', $location);
            }

            $moduleLimit = $this->getParam($params, 'module', null);

            $modules = ModuleQuery::getActivated();

            foreach ($modules as $module) {

                if(null !== $moduleLimit && $moduleLimit != $module->getCode()) {
                    continue;
                }

                $file = sprintf("%s/AdminIncludes/%s.html", $module->getAbsoluteBaseDir(), $location);

                if (file_exists($file)) {
                    $content .= file_get_contents($file);
                }
            }
        }

        if (! empty($content))
            return $template->fetch(sprintf("string:%s", $content));

        return "";
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'module_include', $this, 'theliaModule'),
        );
    }
}

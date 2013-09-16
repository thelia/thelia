<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace DebugBar\Smarty\Plugin;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\an;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use DebugBar\DebugBar as BaseDebugBar;

/**
 * Class DebugBar
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class DebugBar extends AbstractSmartyPlugin
{
    protected $debugBar;
    protected $debugMode;

    public function __construct(BaseDebugBar $debugbar, $debugMode)
    {
        $this->debugBar = $debugbar;
        $this->debugMode = $debugMode;
    }

    public function render($params, \Smarty_Internal_Template $template)
    {
        $render = "";
        if ($this->debugMode) {
            $render = $this->debugBar->getJavascriptRenderer()->render();
        }

        return $render;
    }

    public function renderHead($params, \Smarty_Internal_Template $template)
    {
        $render = "";
        if ($this->debugMode) {
            $javascriptRenderer = $this->debugBar->getJavascriptRenderer();
            $assets = $javascriptRenderer->getAsseticCollection();

            $cssCollection = $assets[0];
            $jsCollection = $assets[1];

            $render .= sprintf('<style media="screen" type="text/css">%s</style>', $cssCollection->dump());
            $render .= sprintf('<script>%s</script>', $jsCollection->dump());
        }

        return $render;
    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("function", "debugbar_renderHead", $this, "renderHead"),
            new SmartyPluginDescriptor("function", "debugbar_render", $this, "render")
        );
    }
}
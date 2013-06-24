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
use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Core\Template\Smarty\Assets\SmartyAssetsManager;

class Assetic implements SmartyPluginInterface
{
    public $asset_manager;

    public function __construct()
    {
        $web_root  = THELIA_WEB_DIR;

        $asset_dir_from_web_root = 'assets/admin/default'; // FIXME

        $this->asset_manager = new SmartyAssetsManager($web_root, $asset_dir_from_web_root);
    }

    public function theliaBlockJavascripts($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall('js', $params, $content, $template, $repeat);
    }

    public function theliaBlockImages($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall(SmartyAssetsManager::ASSET_TYPE_AUTO, $params, $content, $template, $repeat);
    }

    public function theliaBlockStylesheets($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall('css', $params, $content, $template, $repeat);
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('block', 'stylesheets', $this, 'theliaBlockStylesheets'),
            new SmartyPluginDescriptor('block', 'javascripts', $this, 'theliaBlockJavascripts'),
            new SmartyPluginDescriptor('block', 'images'     , $this, 'theliaBlockImages')
        );
    }
}

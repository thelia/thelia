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

namespace Thelia\Core\Template\Smarty\Assets;

use Thelia\Core\Template\Assets\AsseticHelper;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class SmartyAssetsManager
{
    const ASSET_TYPE_AUTO = '';

    private $assetic_manager;

    private $web_root;
    private $path_relative_to_web_root;

    /**
     * Creates a new SmartyAssetsManager instance
     *
     * @param string $web_root                  the disk path to the web root
     * @param string $path_relative_to_web_root the path (relative to web root) where the assets will be generated
     */
    public function __construct($web_root, $path_relative_to_web_root)
    {
        $this->web_root = $web_root;
        $this->path_relative_to_web_root = $path_relative_to_web_root;

        $this->assetic_manager = new AsseticHelper();
    }

    public function processSmartyPluginCall($assetType, $params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        // Opening tag (first call only)
        if ($repeat) {
            $file    = $params['file'];
            $filters = isset($params['filters']) ? $params['filters'] : '';
            $debug   = isset($params['debug']) ? trim(strtolower($params['debug'])) == 'true' : false;

            // Get template base path
            $tpl_path = $template->source->filepath;

            // Get basedir
            $tpl_dir = dirname($tpl_path);

            // Create absolute dir path
            $asset_dir  = realpath($tpl_dir.'/'.dirname($file));
            $asset_file = basename($file);

            if ($asset_dir === false) throw new \Exception("Failed to get real path of '".$tpl_dir.'/'.dirname($file)."'");

            $url = $this->assetic_manager->asseticize(
                    $asset_dir.'/'.$asset_file,
                    $this->web_root."/".$this->path_relative_to_web_root,
                    URL::absoluteUrl($this->path_relative_to_web_root, array(), true /* path only */),
                    $assetType,
                    $filters,
                    $debug
             );

            $template->assign('asset_url', $url);
        } elseif (isset($content)) {
            return $content;
        }
    }
}

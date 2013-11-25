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
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Tools\URL;
use Thelia\Core\Template\Assets\AssetManagerInterface;

class SmartyAssetsManager
{
    const ASSET_TYPE_AUTO = '';

    private $assetsManager;

    private $web_root;
    private $path_relative_to_web_root;

    static private $assetsDirectory = null;

    /**
     * Creates a new SmartyAssetsManager instance
     *
     * @param AssetManagerInterface $assetsManager an asset manager instance
     * @param string  $web_root                    the disk path to the web root (with final /)
     * @param string  $path_relative_to_web_root   the path (relative to web root) where the assets will be generated
     */
    public function __construct(AssetManagerInterface $assetsManager, $web_root, $path_relative_to_web_root)
    {
        $this->web_root = $web_root;
        $this->path_relative_to_web_root = $path_relative_to_web_root;

        $this->assetsManager = $assetsManager;
    }

    public function prepareAssets($assets_directory, \Smarty_Internal_Template $template)
    {
        self::$assetsDirectory = $assets_directory;
        $smartyParser = $template->smarty;
        $templateDefinition = $smartyParser->getTemplateDefinition();
        switch($templateDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                $frontOfficeTemplateDirectories = $smartyParser->getFrontOfficeTemplateDirectories();
                if(isset($frontOfficeTemplateDirectories[$templateDefinition->getName()])) {
                    /* create assets foreach directory : main @ modules */
                    foreach($frontOfficeTemplateDirectories[$templateDefinition->getName()] as $key => $directory) {
                        $tpl_path = $directory . DS . self::$assetsDirectory;
                        $asset_dir_absolute_path = realpath($tpl_path);
                        if(false !== $asset_dir_absolute_path) {
                            $this->assetsManager->prepareAssets(
                                $asset_dir_absolute_path,
                                $this->web_root . $this->path_relative_to_web_root,
                                $templateDefinition->getPath(),
                                $key
                            );
                        }
                    }
                }
                break;

            case TemplateDefinition::BACK_OFFICE:
                $backOfficeTemplateDirectories = $smartyParser->getBackOfficeTemplateDirectories();
                if(isset($backOfficeTemplateDirectories[$templateDefinition->getName()])) {
                    /* create assets foreach directory : main @ modules */
                    foreach($backOfficeTemplateDirectories[$templateDefinition->getName()] as $key => $directory) {
                        $tpl_path = $directory . DS . self::$assetsDirectory;
                        $asset_dir_absolute_path = realpath($tpl_path);
                        if(false !== $asset_dir_absolute_path) {
                            $this->assetsManager->prepareAssets(
                                $asset_dir_absolute_path,
                                $this->web_root . $this->path_relative_to_web_root,
                                $templateDefinition->getPath(),
                                $key
                            );
                        }
                    }
                }
                break;

            case TemplateDefinition::PDF:
                break;

            default:
                break;
        }

//        $tpl_dir = dirname($template->source->filepath);
//
//        $tpl_path = $tpl_dir . DS . self::$assetsDirectory;
//        $asset_dir_absolute_path = realpath($tpl_path);
//        if ($asset_dir_absolute_path === false) {
//            /* no assets for current template */
//            $tpl_path = THELIA_TEMPLATE_DIR . DS . $template->smarty->getTemplate();
//            $asset_dir_absolute_path = realpath($tpl_path);
//        }
//
//        if ($asset_dir_absolute_path === false) {
//            throw new \Exception("Failed to get real path of '" . $tpl_path . "'");
//        }
//
//        $this->assetsManager->prepareAssets(
//                $asset_dir_absolute_path,
//                $this->web_root . $this->path_relative_to_web_root
//        );
    }

    public function computeAssetUrl($assetType, $params, \Smarty_Internal_Template $template)
    {
        $file           = $params['file'];

        /* we trick here relative thinking for file attribute */
        $file = ltrim($file, '/');
        while(substr($file, 0, 3) == '../') {
            $file = substr($file, 3);
        }

        $assetOrigin    = isset($params['origin']) ? $params['origin'] : "0";
        $filters        = isset($params['filters']) ? $params['filters'] : '';
        $debug          = isset($params['debug']) ? trim(strtolower($params['debug'])) == 'true' : false;

        $smartyParser = $template->smarty;
        $templateDefinition = $smartyParser->getTemplateDefinition();

//        // Get template base path
//        $tpl_path = $template->source->filepath;
//
//        // Get basedir
//        $tpl_dir = dirname($tpl_path);
//
//        // Create absolute dir path
//        $assetDir  = realpath($tpl_dir) . DS . dirname($file);
//        $asset_file = basename($file);

        $assetSource = false;
        switch($templateDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                $frontOfficeTemplateDirectories = $smartyParser->getFrontOfficeTemplateDirectories();
                if(isset($frontOfficeTemplateDirectories[$templateDefinition->getName()][$assetOrigin])) {
                    $assetSource = $frontOfficeTemplateDirectories[$templateDefinition->getName()][$assetOrigin];
                }
                break;

            case TemplateDefinition::BACK_OFFICE:
                $backOfficeTemplateDirectories = $smartyParser->getbackOfficeTemplateDirectories();
                if(isset($backOfficeTemplateDirectories[$templateDefinition->getName()][$assetOrigin])) {
                    $assetSource = $backOfficeTemplateDirectories[$templateDefinition->getName()][$assetOrigin];
                }
                break;

            case TemplateDefinition::PDF:
                break;

            default:
                break;
        }

        if ($assetSource === false) throw new \Exception("Failed to get real path of '/".dirname($file)."'");

        $url = $this->assetsManager->processAsset(
            $assetSource . DS . $file,
            $assetSource . DS . self::$assetsDirectory,
            $this->web_root . $this->path_relative_to_web_root,
            $templateDefinition->getPath(),
            $assetOrigin,
            URL::getInstance()->absoluteUrl($this->path_relative_to_web_root, null, URL::PATH_TO_FILE /* path only */),
            $assetType,
            $filters,
            $debug
         );

        return $url;
    }

    public function processSmartyPluginCall($assetType, $params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        // Opening tag (first call only)
        if ($repeat) {
            $url = $this->computeAssetUrl($assetType, $params, $template);

            $template->assign('asset_url', $url);

        } elseif (isset($content)) {
            return $content;
        }
    }
}

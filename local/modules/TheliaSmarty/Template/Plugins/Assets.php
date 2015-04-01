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

use Thelia\Core\Template\Assets\AssetManagerInterface;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Model\ConfigQuery;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\Assets\SmartyAssetsManager;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Assets extends AbstractSmartyPlugin
{
    public $assetManager;

    public function __construct(AssetManagerInterface $assetsManager, AssetResolverInterface $assetsResolver)
    {
        $asset_dir_from_web_root = ConfigQuery::read('asset_dir_from_web_root', 'assets');

        $this->assetManager = new SmartyAssetsManager(
            $assetsManager,
            $assetsResolver,
            THELIA_WEB_DIR,
            $asset_dir_from_web_root
        );
    }

    public function declareAssets($params, \Smarty_Internal_Template $template)
    {
        if (false !== $asset_dir = $this->getParam($params, 'directory', false)) {
            $this->assetManager->prepareAssets($asset_dir, $template);

            return '';
        }

        throw new \InvalidArgumentException('declare_assets: parameter "directory" is required');
    }

    public function blockJavascripts($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->assetManager->processSmartyPluginCall('js', $params, $content, $template, $repeat);
    }

    public function blockImages($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this
            ->assetManager
            ->processSmartyPluginCall(SmartyAssetsManager::ASSET_TYPE_AUTO, $params, $content, $template, $repeat);
    }

    public function blockStylesheets($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->assetManager->processSmartyPluginCall('css', $params, $content, $template, $repeat);
    }

    public function functionImage($params, \Smarty_Internal_Template $template)
    {
        return $this->assetManager->computeAssetUrl(SmartyAssetsManager::ASSET_TYPE_AUTO, $params, $template);
    }

    public function functionAsset($params, \Smarty_Internal_Template $template)
    {
        return $this->assetManager->computeAssetUrl(SmartyAssetsManager::ASSET_TYPE_AUTO, $params, $template, false);
    }

    public function functionJavascript($params, \Smarty_Internal_Template $template)
    {
        return $this->assetManager->computeAssetUrl(SmartyAssetsManager::ASSET_TYPE_AUTO, $params, $template);
    }

    public function functionStylesheet($params, \Smarty_Internal_Template $template)
    {
        return $this->assetManager->computeAssetUrl('css', $params, $template);
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('block', 'stylesheets', $this, 'blockStylesheets'),
            new SmartyPluginDescriptor('block', 'javascripts', $this, 'blockJavascripts'),
            new SmartyPluginDescriptor('block', 'images', $this, 'blockImages'),

            new SmartyPluginDescriptor('function', 'asset', $this, 'functionAsset'),
            new SmartyPluginDescriptor('function', 'image', $this, 'functionImage'),
            new SmartyPluginDescriptor('function', 'javascript', $this, 'functionJavascript'),
            new SmartyPluginDescriptor('function', 'stylesheet', $this, 'functionStylesheet'),

            new SmartyPluginDescriptor('function', 'declare_assets', $this, 'declareAssets')
        );
    }
}

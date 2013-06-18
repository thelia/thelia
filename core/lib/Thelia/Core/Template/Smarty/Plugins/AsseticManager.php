<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 18/06/13
 * Time: 22:44
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\Template\Smarty\Plugins;


use Thelia\Core\Template\Smarty\RegisterSmartyPlugin;
use Thelia\Core\Template\Smarty\SmartyPluginInterface;
use Thelia\Admin\Templating\Smarty\AssetsManager;

class AsseticManager implements SmartyPluginInterface{

    public $asset_manager;

    public function __construct()
    {
        $web_root  = THELIA_WEB_DIR;

        $asset_dir_from_web_root = 'assets/admin/default'; // FIXME

        $this->asset_manager = new AssetsManager($web_root, $asset_dir_from_web_root);
    }


    public function smartyBlockJavascripts($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall('js', $params, $content, $template, $repeat);
    }

    public function smartyBlockImages($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall(AssetsManager::ASSET_TYPE_AUTO, $params, $content, $template, $repeat);
    }

    public function smartyBlockStylesheets($params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        return $this->asset_manager->processSmartyPluginCall('css', $params, $content, $template, $repeat);
    }

    /**
     * @return mixed
     */
    public function registerPlugins()
    {
        return array(
            new RegisterSmartyPlugin('block', 'stylesheets', $this, 'smartyBlockStylesheets'),
            new RegisterSmartyPlugin('block', 'javascripts', $this, 'smartyBlockJavascripts'),
            new RegisterSmartyPlugin('block', 'images', $this, 'smartyBlockImages')
        );
    }
}
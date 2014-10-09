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

namespace TheliaDebugBar\Smarty\Plugin;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\an;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use DebugBar\DebugBar as BaseDebugBar;
use Thelia\Tools\URL;

/**
 * Class DebugBar
 * @author Manuel Raynaud <manu@thelia.net>
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

    public function renderCss($params, \Smarty_Internal_Template $template)
    {
        $render = "";
        if($this->debugMode)
        {
            $webFile = "cache/debugbar.css";
            $cssFile = THELIA_WEB_DIR ."/".$webFile;

            if(!file_exists($cssFile)) {
                $javascriptRenderer = $this->debugBar->getJavascriptRenderer();
                $assetCss = $javascriptRenderer->getAsseticCollection("css");

                foreach($assetCss->all() as $asset) {
                    if(strpos($asset->getSourcePath(), "font-awesome") !== false) {
                        $assetCss->removeLeaf($asset);
                    }
                }

                if(!file_exists(THELIA_WEB_DIR . "/cache")) {
                    @mkdir(THELIA_WEB_DIR . "/cache");
                }

                @file_put_contents($cssFile, $assetCss->dump());
            }
            $render =  sprintf('<link rel="stylesheet" href="%s">', URL::getInstance()->absoluteUrl($webFile, array(), URL::PATH_TO_FILE));
        }
        return $render;
    }

    public function renderJs($params, \Smarty_Internal_Template $template)
    {
        $render = "";
        if($this->debugMode)
        {
            $webFile = "cache/debugbar.js";
            $cacheFile = THELIA_WEB_DIR ."/".$webFile;

            if (!file_exists($cacheFile)) {
                $javascriptRenderer = $this->debugBar->getJavascriptRenderer();
                $assetJs = $javascriptRenderer->getAsseticCollection("js");

                foreach($assetJs->all() as $asset) {
                    if(strpos($asset->getSourcePath(), "jquery") !== false) {
                        $assetJs->removeLeaf($asset);
                    }
                }

                if(!file_exists(THELIA_WEB_DIR . "/cache")) {
                    @mkdir(THELIA_WEB_DIR . "/cache");
                }

                @file_put_contents($cacheFile, $assetJs->dump());
            }

            $render = sprintf('<script src="%s"></script>', URL::getInstance()->absoluteUrl($webFile, array(), URL::PATH_TO_FILE));
        }
        return $render;
    }

    /**
     * @return an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor("function", "debugbar_rendercss", $this, "renderCss"),
            new SmartyPluginDescriptor("function", "debugbar_renderjs", $this, "renderJs"),
            new SmartyPluginDescriptor("function", "debugbar_renderresult", $this, "render")
        );
    }
}
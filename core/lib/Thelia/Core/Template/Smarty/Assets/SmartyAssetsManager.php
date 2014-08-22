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

namespace Thelia\Core\Template\Smarty\Assets;

use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Tools\URL;
use Thelia\Core\Template\Assets\AssetManagerInterface;

class SmartyAssetsManager
{
    const ASSET_TYPE_AUTO = '';

    private $assetsManager;

    private $web_root;
    private $path_relative_to_web_root;

    private static $assetsDirectory = null;

    /**
     * Creates a new SmartyAssetsManager instance
     *
     * @param AssetManagerInterface $assetsManager             an asset manager instance
     * @param string                $web_root                  the disk path to the web root (with final /)
     * @param string                $path_relative_to_web_root the path (relative to web root) where the assets will be generated
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

        // Get the registered template directories for the current template path
        $templateDirectories = $smartyParser->getTemplateDirectories($templateDefinition->getType());

        if (isset($templateDirectories[$templateDefinition->getName()])) {

            /* create assets foreach registered directory : main @ modules */
            foreach ($templateDirectories[$templateDefinition->getName()] as $key => $directory) {

                $tpl_path = $directory . DS . self::$assetsDirectory;

                $asset_dir_absolute_path = realpath($tpl_path);

                if (false !== $asset_dir_absolute_path) {

                    $this->assetsManager->prepareAssets(
                        $asset_dir_absolute_path,
                        $this->web_root . $this->path_relative_to_web_root,
                        $templateDefinition->getPath(),
                        $key
                    );
                }
            }
        }
    }

    /**
     * Retrieve asset URL
     *
     * @param string                    $assetType js|css|image
     * @param array                     $params    Parameters
     *                                             - file File path in the default template
     *                                             - source module asset
     *                                             - filters filter to apply
     *                                             - debug
     *                                             - template if you want to load asset from another template
     * @param \Smarty_Internal_Template $template  Smarty Template
     *
     * @return string
     * @throws \Exception
     */
    public function computeAssetUrl($assetType, $params, \Smarty_Internal_Template $template)
    {
        $file             = $params['file'];
        $assetOrigin      = isset($params['source']) ? $params['source'] : "0";
        $filters          = isset($params['filters']) ? $params['filters'] : '';
        $debug            = isset($params['debug']) ? trim(strtolower($params['debug'])) == 'true' : false;
        $webAssetTemplate = isset($params['template']) ? $params['template'] : false;

        $assetSource = "";

        /* we trick here relative thinking for file attribute */
        $file = ltrim($file, '/');
        while (substr($file, 0, 3) == '../') {
            $file = substr($file, 3);
        }

        $smartyParser = $template->smarty;
        /** @var \Thelia\Core\Template\Smarty\SmartyParser $templateDefinition */
        $templateDefinition = $smartyParser->getTemplateDefinition($webAssetTemplate);

        $templateDirectories = $smartyParser->getTemplateDirectories($templateDefinition->getType());

        // if it's not a custom template and looking for a different origin (e.g. module)
        // we first check if the asset is present in the default "source" (template)
        // if not we take the default asset from the assetOrigin (module)

        $paths = $this->getFallbackSources($templateDirectories, $templateDefinition->getName(), $assetOrigin);

        foreach ($paths as $path) {
            if ($this->filesExist($path, $file)) {
                $assetSource = $path;
                break;
            }
        }

        if (DS != '/') {
            // Just to be sure to generate a clean pathname
            $file = str_replace('/', DS, $file);
        }

        $url = "";

        // test if file exists before running the process

        if ("" !== $assetSource) {
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
        } else {
            Tlog::getInstance()->addError("Asset $file was not found.");
        }

        return $url;
    }

    /**
     * Get all possible directories from which the asset can be found.
     * It returns an array of directories ordered by priority.
     *
     * @param  array  $directories all directories source available for the template type
     * @param  string $template    the name of the template
     * @param  string $source      the module code or "0"
     * @return array  possible directories
     */
    protected function getFallbackSources($directories, $template, $source)
    {
        $paths = [];

        if ("0" !== $source) {

            if (isset($directories[$template]["0"])) {
                $paths[] = $directories[$template]["0"] . DS
                    . TemplateDefinition::HOOK_OVERRIDE_SUBDIR . DS
                    .$source;
            }

            if (isset($directories[$template][$source])) $paths[] = $directories[$template][$source];

            if (isset($directories[TemplateDefinition::HOOK_DEFAULT_THEME][$source])) $paths[] = $directories[TemplateDefinition::HOOK_DEFAULT_THEME][$source];

        } else {

            $paths[] = $directories[$template]["0"];

        }

        return $paths;
    }

    /**
     * Check if a file(s) exists in a directory
     *
     * @param  string $dir  the directory path
     * @param  string $file the file path. It can contain wildcard. eg: /path/*.css
     * @return bool   true if file(s)
     */
    protected function filesExist($dir, $file)
    {
        if (!file_exists($dir)) return false;

        $finder = new Finder();
        $files = $finder->files()->in($dir);

        $pos = strrpos($file, DS);
        if ($pos !== false) {
            $files = $files->path(substr($file, 0, $pos))
                ->name(substr($file, $pos + 1));
        } else {
           $files = $files->name($file);
        }

        return ($files->count() != 0);
    }

    public function processSmartyPluginCall($assetType, $params, $content, \Smarty_Internal_Template $template, &$repeat)
    {
        // Opening tag (first call only)
        if ($repeat) {
            $url = "";
            try {
                $url = $this->computeAssetUrl($assetType, $params, $template);
            } catch (\Exception $ex) {
                Tlog::getInstance()->addWarning("Failed to get real path of " . $params['file']);
            }
            $template->assign('asset_url', $url);
        } elseif (isset($content)) {
            return $content;
        }
    }
}

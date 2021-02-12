<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaSmarty\Template\Assets;

use Thelia\Core\Template\Assets\AssetManagerInterface;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class SmartyAssetsResolver implements AssetResolverInterface
{
    protected $path_relative_to_web_root;

    protected $assetsManager;

    protected $cdnBaseUrl;

    /**
     * Creates a new SmartyAssetsManager instance.
     *
     * @param AssetManagerInterface $assetsManager an asset manager instance
     */
    public function __construct(AssetManagerInterface $assetsManager)
    {
        $this->path_relative_to_web_root = ConfigQuery::read('asset_dir_from_web_root', 'assets');

        $this->assetsManager = $assetsManager;

        $this->cdnBaseUrl = ConfigQuery::read('cdn.assets-base-url', null);
    }

    /**
     * @param string $url the fully qualified CDN URL that will be used to create doucments URL
     */
    public function setCdnBaseUrl($url)
    {
        $this->cdnBaseUrl = $url;
    }

    /**
     * Generate an asset URL.
     *
     * @param string          $source                  a module code, or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param string          $file                    the file path, relative to a template base directory (e.g. assets/css/style.css)
     * @param string          $type                    the asset type, either 'css' or '
     * @param ParserInterface $parserInterface         the current template parser
     * @param array           $filters                 the filters to pass to the asset manager
     * @param bool            $debug                   the debug mode
     * @param string          $declaredAssetsDirectory if not null, this is the assets directory declared in the {declare_assets} function of a template
     * @param mixed           $sourceTemplateName      A template name, of false. If provided, the assets will be searched in this template directory instead of the current one.
     */
    public function resolveAssetURL($source, $file, $type, ParserInterface $parserInterface, $filters = [], $debug = false, $declaredAssetsDirectory = null, $sourceTemplateName = false)
    {
        $url = '';

        // Normalize path separator
        $file = $this->fixPathSeparator($file);

        $templateDefinition = $parserInterface->getTemplateDefinition($sourceTemplateName);

        $fileRoot = $this->resolveAssetSourcePathAndTemplate($source, $sourceTemplateName, $file, $parserInterface, $templateDefinition);

        if (null !== $fileRoot) {
            $url = $this->assetsManager->processAsset(
                $fileRoot.DS.$file,
                $fileRoot,
                THELIA_WEB_DIR.$this->path_relative_to_web_root,
                $templateDefinition->getPath(),
                $source,
                URL::getInstance()->absoluteUrl($this->path_relative_to_web_root, null, URL::PATH_TO_FILE /* path only */ , $this->cdnBaseUrl),
                $type,
                $filters,
                $debug
            );
        } else {
            Tlog::getInstance()->addError("Asset $file (type $type) was not found.");
        }

        return $url;
    }

    /**
     * Return an asset source file path.
     *
     * A system of fallback enables file overriding. It will look for the template :
     *      - in the current template in directory /modules/{module code}/
     *      - in the module in the current template if it exists
     *      - in the module in the default template
     *
     * @param string          $source          a module code, or or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param string          $templateName    a template name, or false to use the current template
     * @param string          $fileName        the filename
     * @param ParserInterface $parserInterface the current template parser
     *
     * @return mixed the path to directory containing the file, or null if the file doesn't exists
     */
    public function resolveAssetSourcePath($source, $templateName, $fileName, ParserInterface $parserInterface)
    {
        $tpl = $parserInterface->getTemplateDefinition(false);

        return $this->resolveAssetSourcePathAndTemplate(
            $source,
            $templateName,
            $fileName,
            $parserInterface,
            $tpl
        );
    }

    /**
     * Return an asset source file path, and get the original template where it was found.
     *
     * A system of fallback enables file overriding. It will look for the template :
     *      - in the current template in directory /modules/{module code}/
     *      - in the parent templates (if any) in directory /modules/{module code}/
     *      - in the module in the current template if it exists
     *      - in the module in the default template
     *
     * @param string             $source              a module code, or or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param string             $templateName        a template name, or false to use the current template
     * @param string             $fileName            the filename
     * @param ParserInterface    $parserInterface     the current template parser
     * @param TemplateDefinition &$templateDefinition the template where to start search.
     *                                                This parameter will contain the template where the asset was found.
     *
     * @return mixed the path to directory containing the file, or null if the file doesn't exists
     */
    public function resolveAssetSourcePathAndTemplate(
        $source,
        $templateName,
        $fileName,
        ParserInterface $parserInterface,
        TemplateDefinition &$templateDefinition
    ) {
        // A simple cache for the path list, to gain some performances
        static $cache = [];

        // The path are categorized, and will be checked in the following order. (see getPossibleAssetSources)
        // - template : the template directory
        // - module_override : the module override directory (template/modules/module_name/...)
        // - module_directory : the current template in the module directory
        // - the default template in the module directory.
        static $pathOrigin = ['template', 'module_override', 'module_directory', 'default_fallback'];

        $templateName = $templateName ?: $templateDefinition->getName();
        $templateType = $templateDefinition->getType();

        $templateDirectories = $parserInterface->getTemplateDirectories($templateDefinition->getType());

        $hash = "$source|$templateType|$templateName";

        if (!isset($cache[$hash])) {
            // Build a list of all template names, starting with current template
            $templateList = [$templateName => $templateDefinition];

            /** @var TemplateDefinition $tplDef */
            foreach ($templateDefinition->getParentList() as $tplDef) {
                $templateList[$tplDef->getName()] = $tplDef;
            }

            $pathList = [];

            // Get all possible directories to search, including the parent templates ones.
            // The current template is checked firts, then the parent ones.

            /* @var TemplateDefinition $templateDef */
            foreach ($templateList as $tplName => $dummy) {
                $this->getPossibleAssetSources(
                    $templateDirectories,
                    $tplName,
                    $source,
                    $pathList
                );
            }

            $cache[$hash] = [$pathList, $templateList];
        } else {
            [$pathList, $templateList] = $cache[$hash];
        }

        // Normalize path separator if required (e.g., / becomes \ on windows)
        $fileName = $this->fixPathSeparator($fileName);

        /* Absolute paths are not allowed. This may be a mistake, such as '/assets/...' instead of 'assets/...'. Forgive it.  */
        $fileName = ltrim($fileName, DS);

        /* Navigating in the server's directory tree is not allowed :) */
        if (preg_match('!\.\.\\'.DS.'!', $fileName)) {
            // This time, we will not forgive.
            throw new \InvalidArgumentException('Relative paths are not allowed in assets names.');
        }

        // Find the first occurrence of the file in the directory list.
        foreach ($pathOrigin as $origin) {
            if (isset($pathList[$origin])) {
                foreach ($pathList[$origin] as $pathInfo) {
                    [$tplName, $path] = $pathInfo;

                    if ($this->filesExist($path, $fileName)) {
                        // Got it ! Save the template where the asset was found and return !
                        $templateDefinition = $templateList[$tplName];

                        return $path;
                    }
                }
            }
        }

        // Not found !
        return null;
    }

    /**
     * Be sure that the pat separator of a pathname is always the platform path separator.
     *
     * @param string $path the iput path
     *
     * @return string the fixed path
     */
    protected function fixPathSeparator($path)
    {
        if (DS != '/') {
            $path = str_replace('/', DS, $path);
        }

        return $path;
    }

    /**
     * Check if a file(s) exists in a directory.
     *
     * @param string $dir  the directory path
     * @param string $file the file path. It can contain wildcard. eg: /path/*.css
     *
     * @return bool true if file(s)
     */
    protected function filesExist($dir, $file)
    {
        if (!file_exists($dir)) {
            return false;
        }

        $full_path = rtrim($dir, DS).DS.ltrim($file, DS);

        try {
            $files = glob($full_path);

            $files_found = !empty($files);
        } catch (\Exception $ex) {
            Tlog::getInstance()->addError($ex->getMessage());

            $files_found = false;
        }

        return $files_found;
    }

    /**
     * Get all possible directories from which the asset can be found.
     * It returns an array of directories ordered by priority.
     *
     * @param array  $directories  all directories source available for the template type
     * @param string $templateName the name of the template
     * @param string $source       the module code or ParserInterface::TEMPLATE_ASSETS_KEY
     * @param array  $pathList     the pathList that will be updated
     */
    protected function getPossibleAssetSources($directories, $templateName, $source, &$pathList)
    {
        if ($source !== ParserInterface::TEMPLATE_ASSETS_KEY) {
            // We're in a module.

            // First look into the current template in the right scope : frontOffice, backOffice, ...
            // template should be overridden in : {template_path}/modules/{module_code}/{template_name}
            if (isset($directories[$templateName][ParserInterface::TEMPLATE_ASSETS_KEY])) {
                $pathList['module_override'][] = [
                    $templateName,
                    $directories[$templateName][ParserInterface::TEMPLATE_ASSETS_KEY]
                    .DS
                    .self::MODULE_OVERRIDE_DIRECTORY_NAME.DS
                    .$source,
                ];
            }

            // then in the implementation for the current template used in the module directory
            if (isset($directories[$templateName][$source])) {
                $pathList['module_directory'][] = [$templateName, $directories[$templateName][$source]];
            }

            // then in the default theme in the module itself
            if (isset($directories[self::DEFAULT_TEMPLATE_NAME][$source])) {
                $pathList['default_fallback'][] = [$templateName, $directories[self::DEFAULT_TEMPLATE_NAME][$source]];
            }
        } else {
            $pathList['template'][] = [$templateName, $directories[$templateName][$source]];
        }
    }
}

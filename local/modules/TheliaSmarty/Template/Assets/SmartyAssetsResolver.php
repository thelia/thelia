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

namespace TheliaSmarty\Template\Assets;

use Thelia\Core\Template\Assets\AssetManagerInterface;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;
use TheliaSmarty\Template\SmartyParser;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class SmartyAssetsResolver implements AssetResolverInterface
{
    private $path_relative_to_web_root;

    private $assetsManager;

    /**
     * Creates a new SmartyAssetsManager instance
     *
     * @param AssetManagerInterface $assetsManager an asset manager instance
      */
    public function __construct(AssetManagerInterface $assetsManager)
    {
        $this->path_relative_to_web_root = ConfigQuery::read('asset_dir_from_web_root', 'assets');

        $this->assetsManager = $assetsManager;
    }

    /**
     * Generate an asset URL
     *
     * @param string $source a module code, or SmartyParser::TEMPLATE_ASSETS_KEY
     * @param string $file the file path, relative to a template base directory (e.g. assets/css/style.css)
     * @param string $type the asset type, either 'css' or '
     * @param ParserInterface $parserInterface the current template parser
     * @param array $filters the filters to pass to the asset manager
     * @param bool $debug the debug mode
     * @param string $declaredAssetsDirectory if not null, this is the assets directory declared in the {declare_assets} function of a template.
     * @param mixed $sourceTemplateName A template name, of false. If provided, the assets will be searched in this template directory instead of the current one.
     * @return mixed
     */
    public function resolveAssetURL($source, $file, $type, ParserInterface $parserInterface, $filters = [], $debug = false, $declaredAssetsDirectory = null, $sourceTemplateName = false)
    {
        $url = "";

        // Normalize path separator
        $file = $this->fixPathSeparator($file);

        $fileRoot = $this->resolveAssetSourcePath($source, $sourceTemplateName, $file, $parserInterface);

        if (null !== $fileRoot) {
            $templateDefinition = $parserInterface->getTemplateDefinition($sourceTemplateName);

            $url = $this->assetsManager->processAsset(
                $fileRoot . DS . $file,
                $fileRoot,
                THELIA_WEB_DIR . $this->path_relative_to_web_root,
                $templateDefinition->getPath(),
                $source, // $this->getBaseWebAssetDirectory($source, $declaredAssetsDirectory),
                URL::getInstance()->absoluteUrl($this->path_relative_to_web_root, null, URL::PATH_TO_FILE /* path only */),
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
     * @param  string $source a module code, or or SmartyParser::TEMPLATE_ASSETS_KEY
     * @param  string $templateName a template name, or false to use the current template
     * @param  string $fileName the filename
     * @param  ParserInterface $parserInterface the current template parser
     *
     * @return mixed the path to directory containing the file, or null if the file doesn't exists.
     */
    public function resolveAssetSourcePath($source, $templateName, $fileName, ParserInterface $parserInterface)
    {
        $filePath = null;

        $templateDefinition = $parserInterface->getTemplateDefinition(false);

        // Get all possible directories to search
        $paths = $this->getPossibleAssetSources(
            $parserInterface->getTemplateDirectories($templateDefinition->getType()),
            $templateName ?: $templateDefinition->getName(),
            $source
        );

        // Normalize path separator if required (e.g., / becomes \ on windows)
        $fileName = $this->fixPathSeparator($fileName);

        /* Absolute paths are not allowed. This may be a mistake, such as '/assets/...' instead of 'assets/...'. Forgive it.  */
        $fileName = ltrim($fileName, DS);

        /* Navigating in the server's directory tree is not allowed :) */
        if (preg_match('!\.\.\\'.DS.'!', $fileName)) {
            // This time, we will not forgive.
            throw new \InvalidArgumentException("Relative paths are not allowed in assets names.");
        }

        // Find the first occurrence of the file in the directories lists
        foreach ($paths as $path) {
            if ($this->filesExist($path, $fileName)) {
                // Got it !
                $filePath = $path;
                break;
            }
        }

        return $filePath;
    }


    /**
     * Be sure that the pat separator of a pathname is always the platform path separator.
     *
     * @param string $path the iput path
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
     * Check if a file(s) exists in a directory
     *
     * @param  string $dir  the directory path
     * @param  string $file the file path. It can contain wildcard. eg: /path/*.css
     * @return bool   true if file(s)
     */
    protected function filesExist($dir, $file)
    {
        if (!file_exists($dir)) {
            return false;
        }

        $full_path = rtrim($dir, DS) . DS . ltrim($file, DS);

        try {
            $files = glob($full_path);

            $files_found = ! empty($files);
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
     * @param  array  $directories all directories source available for the template type
     * @param  string $template    the name of the template
     * @param  string $source      the module code or SmartyParser::TEMPLATE_ASSETS_KEY
     * @return array  possible directories
     */
    protected function getPossibleAssetSources($directories, $template, $source)
    {
        $paths = [];

        if (SmartyParser::TEMPLATE_ASSETS_KEY !== $source) {
            // We're in a module.

            // First look into the current template in the right scope : frontOffice, backOffice, ...
            // template should be overridden in : {template_path}/modules/{module_code}/{template_name}
            if (isset($directories[$template][SmartyParser::TEMPLATE_ASSETS_KEY])) {
                $paths[] =
                    $directories[$template][SmartyParser::TEMPLATE_ASSETS_KEY]
                    . DS
                    . self::MODULE_OVERRIDE_DIRECTORY_NAME . DS
                    . $source;
            }

            // then in the implementation for the current template used in the module directory
            if (isset($directories[$template][$source])) {
                $paths[] = $directories[$template][$source];
            }

            // then in the default theme in the module itself
            if (isset($directories[self::DEFAULT_TEMPLATE_NAME][$source])) {
                $paths[] = $directories[self::DEFAULT_TEMPLATE_NAME][$source];
            }
        } else {
            $paths[] = $directories[$template][SmartyParser::TEMPLATE_ASSETS_KEY];
        }

        return $paths;
    }
}

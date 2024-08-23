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

namespace TwigEngine\Template;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Core\Template\Assets\AssetManagerInterface;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

#[AutoconfigureTag('thelia.parser.asset')]
class TwigAssetsResolver implements AssetResolverInterface
{
    protected ?string $pathRelativeToWebRoot = null;
    protected ?string $cdnBaseUrl = null;

    public function __construct(
        protected AssetManagerInterface $assetsManager
    ) {
        $this->pathRelativeToWebRoot = ConfigQuery::read('asset_dir_from_web_root', 'assets');
        $this->cdnBaseUrl = ConfigQuery::read('cdn.assets-base-url');
    }

    public function setCdnBaseUrl(string $url): void
    {
        $this->cdnBaseUrl = $url;
    }

    public function resolveAssetURL(
        $source,
        $file,
        $type,
        ParserInterface $parserInterface,
        $filters = [],
        $debug = false,
        $declaredAssetsDirectory = null,
        $sourceTemplateName = false
    ): string {
        // Shortcut external uri resolving
        if (preg_match('#^(https?:)?//#', $file)) {
            return $file;
        }

        $url = '';

        // Normalize path separator
        $file = $this->fixPathSeparator($file);

        $templateDefinition = $parserInterface->getTemplateDefinition($sourceTemplateName);
        $fileRoot = $this->resolveAssetSourcePathAndTemplate($source, $sourceTemplateName, $file, $parserInterface, $templateDefinition);

        if (null !== $fileRoot) {
            $url = $this->assetsManager->processAsset(
                $fileRoot.DS.$file,
                $fileRoot,
                THELIA_WEB_DIR.$this->pathRelativeToWebRoot,
                $templateDefinition->getPath(),
                $source,
                URL::getInstance()->absoluteUrl($this->pathRelativeToWebRoot, null, URL::PATH_TO_FILE /* path only */ , $this->cdnBaseUrl),
                $type,
                $filters,
                $debug
            );
        } else {
            Tlog::getInstance()->addError("Asset $file (type $type) was not found.");
        }

        return $url;
    }

    public function resolveAssetSourcePath(
        $source,
        $templateName,
        $fileName,
        ParserInterface $parserInterface
    ) {
        $tpl = $parserInterface->getTemplateDefinition();

        return $this->resolveAssetSourcePathAndTemplate(
            $source,
            $templateName,
            $fileName,
            $parserInterface,
            $tpl
        );
    }

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

        /* Absolute paths are not allowed. This may be a mistake, such as '/assets/...' instead of 'assets/...'. Forgive it. */
        $fileName = ltrim($fileName, DS);

        /* Navigating in the server's directory tree is not allowed :) */
        if (preg_match('!\.\.'.DS.'!', $fileName)) {
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

    protected function fixPathSeparator($path)
    {
        if (DS !== '/') {
            $path = str_replace('/', DS, $path);
        }

        return $path;
    }

    protected function filesExist($dir, $file): bool
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

    protected function getPossibleAssetSources($directories, $templateName, $source, &$pathList): void
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

    public function supportParser(ParserInterface $parser): bool
    {
        return TwigParser::class === $parser::class;
    }
}

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
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use TheliaSmarty\Template\SmartyParser;

class SmartyAssetsManager
{
    public const ASSET_TYPE_AUTO = '';

    private $assetsManager;
    private $assetsResolver;

    private $web_root;
    private $path_relative_to_web_root;

    private static $assetsDirectory = null;

    /**
     * Creates a new SmartyAssetsManager instance
     *
     * @param AssetManagerInterface  $assetsManager   an asset manager instance
     * @param AssetResolverInterface $assetsResolver  an asset resolver instance
     * @param string $web_root the disk path to the web root (with final /)
     * @param string $path_relative_to_web_root the path (relative to web root) where the assets will be generated
     */
    public function __construct(
        AssetManagerInterface $assetsManager,
        AssetResolverInterface $assetsResolver,
        $web_root,
        $path_relative_to_web_root
    ) {
        $this->web_root = $web_root;
        $this->path_relative_to_web_root = $path_relative_to_web_root;

        $this->assetsManager = $assetsManager;
        $this->assetsResolver = $assetsResolver;
    }

    /**
     * Prepare current template assets
     *
     * @param string $assets_directory the assets directory in the template
     * @param \Smarty_Internal_Template $smarty the smarty parser
     */
    public function prepareAssets($assets_directory, \Smarty_Internal_Template $smarty)
    {
        // Be sure to use the proper path separator
        if (DS != '/') {
            $assets_directory = str_replace('/', DS, $assets_directory);
        }

        // Set the current template assets directory
        self::$assetsDirectory = $assets_directory;

        /** @var SmartyParser $smartyParser */
        $smartyParser = $smarty->smarty;

        $this->prepareTemplateAssets($smartyParser->getTemplateDefinition(), $assets_directory, $smartyParser);
    }

    /**
     * Prepare template assets
     *
     * @param TemplateDefinition $templateDefinition the template to process
     * @param string $assets_directory the assets directory in the template
     * @param \TheliaSmarty\Template\SmartyParser $smartyParser the current parser.
     */
    protected function prepareTemplateAssets(
        TemplateDefinition $templateDefinition,
        $assets_directory,
        SmartyParser $smartyParser
    ) {
        // Get the registered template directories for the current template type
        $templateDirectories = $smartyParser->getTemplateDirectories($templateDefinition->getType());

        // Use the template name first
        $templateDefinitionList = array_merge(
            [ $templateDefinition ],
            $templateDefinition->getParentList()
        );

        // Prepare assets for all template hierarchy.
        // Copy current template assets and its parents assets to the web/assets directory

        /** @var TemplateDefinition $templateDefinitionItem */
        foreach ($templateDefinitionList as $templateDefinitionItem) {
            // Use also the parent directories
            if (isset($templateDirectories[$templateDefinitionItem->getName()])) {
                /* create assets foreach registered directory : main @ modules */
                foreach ($templateDirectories[$templateDefinitionItem->getName()] as $key => $directory) {
                    // This is the assets directory in the template's tree
                    $tpl_path = $directory . DS . $assets_directory;

                    if (false !== $asset_dir_absolute_path = realpath($tpl_path)) {
                        $this->assetsManager->prepareAssets(
                            $asset_dir_absolute_path,
                            $this->web_root . $this->path_relative_to_web_root,
                            $templateDefinitionItem->getPath(),
                            $key . DS . $assets_directory
                        );
                    }
                }
            }
        }
    }

    /**
     * Retrieve asset URL
     *
     * @param string $assetType js|css|image
     * @param array $params Parameters
     *                                             - file File path in the default template
     *                                             - source module asset
     *                                             - filters filter to apply
     *                                             - debug
     *                                             - template if you want to load asset from another template
     * @param \Smarty_Internal_Template $template Smarty Template
     *
     * @param bool $allowFilters if false, the 'filters' parameter is ignored
     * @return string
     * @throws \Exception
     */
    public function computeAssetUrl($assetType, $params, \Smarty_Internal_Template $template, $allowFilters = true)
    {
        $file = $params['file'];

        // The 'file' parameter is mandatory
        if (empty($file)) {
            throw new \InvalidArgumentException(
                "The 'file' parameter is missing in an asset directive (type is '$assetType')"
            );
        }

        $assetOrigin  = $params['source'] ?? ParserInterface::TEMPLATE_ASSETS_KEY;
        $filters      = $allowFilters && isset($params['filters']) ? $params['filters'] : '';
        $debug        = isset($params['debug']) ? trim(strtolower($params['debug'])) == 'true' : false;
        $templateName = $params['template'] ?? false;
        $failsafe     = $params['failsafe'] ?? false;

        Tlog::getInstance()->debug("Searching asset $file in source $assetOrigin, with template $templateName");

        /** @var \TheliaSmarty\Template\SmartyParser $smartyParser */
        $smartyParser = $template->smarty;

        if (false !==  $templateName) {
            // We have to be sure that this external template assets have been properly prepared.
            // We will assume the following:
            //   1) this template have the same type as the current template,
            //   2) this template assets have the same structure as the current template
            //     (which is in self::$assetsDirectory)
            $currentTemplate = $smartyParser->getTemplateDefinition();

            $templateDefinition = new TemplateDefinition(
                $templateName,
                $currentTemplate->getType()
            );

            /* Add this templates directory to the current list */
            $smartyParser->addTemplateDirectory(
                $templateDefinition->getType(),
                $templateDefinition->getName(),
                THELIA_TEMPLATE_DIR . $templateDefinition->getPath(),
                ParserInterface::TEMPLATE_ASSETS_KEY
            );

            $this->prepareTemplateAssets($templateDefinition, self::$assetsDirectory, $smartyParser);
        }

        $assetUrl = $this->assetsResolver->resolveAssetURL(
            $assetOrigin,
            $file,
            $assetType,
            $smartyParser,
            $filters,
            $debug,
            self::$assetsDirectory,
            $templateName
        );

        if (empty($assetUrl)) {
            // Log the problem
            if ($failsafe) {
                // The asset URL will be ''
                Tlog::getInstance()->addWarning("Failed to find asset source file " . $params['file']);
            } else {
                throw new TheliaProcessException("Failed to find asset source file " . $params['file']);
            }
        }

        return $assetUrl;
    }

    /**
     * @param $assetType
     * @param $params
     * @param $content
     * @param $repeat
     * @return null
     * @throws \Exception
     */
    public function processSmartyPluginCall(
        $assetType,
        $params,
        $content,
        \Smarty_Internal_Template $template,
        &$repeat
    ) {
        // Opening tag (first call only)
        if ($repeat) {
            $isfailsafe = false;

            $url = '';
            try {
                // Check if we're in failsafe mode
                if (isset($params['failsafe'])) {
                    $isfailsafe = $params['failsafe'];
                }

                $url = $this->computeAssetUrl($assetType, $params, $template);

                if (empty($url)) {
                    $message = sprintf("Failed to get real path of asset %s without exception", $params['file']);

                    Tlog::getInstance()->addWarning($message);

                    // In debug mode, throw exception
                    if ($this->assetsManager->isDebugMode() && ! $isfailsafe) {
                        throw new TheliaProcessException($message);
                    }
                }
            } catch (\Exception $ex) {
                Tlog::getInstance()->addWarning(
                    sprintf(
                        "Failed to get real path of asset %s with exception: %s",
                        $params['file'],
                        $ex->getMessage()
                    )
                );

                // If we're in development mode, just retrow the exception, so that it will be displayed
                if ($this->assetsManager->isDebugMode() && ! $isfailsafe) {
                    throw $ex;
                }
            }
            $template->assign('asset_url', $url);
        } elseif (isset($content)) {
            return $content;
        }

        return null;
    }
}

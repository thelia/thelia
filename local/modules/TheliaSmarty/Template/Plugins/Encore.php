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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\Assets\EncoreModuleAssetsPathPackage;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Encore extends AbstractSmartyPlugin
{
    public array $packages;

    public string $templateSymlinkDest;

    public string $moduleSymlinkDest;

    public $templateEnv;

    public $activeTemplate;

    public function __construct(
        private TagRenderer $tagRenderer,
        private EntrypointLookupCollectionInterface $entrypointLookupCollection,
        private TemplateHelperInterface $templateHelper,
        private $kernelDebug,
        AdapterInterface $cacheService
    ) {
        $this->packages = [];
        $this->templateEnv = Request::$isAdminEnv ? TemplateDefinition::BACK_OFFICE_SUBDIR : TemplateDefinition::FRONT_OFFICE_SUBDIR;
        $this->activeTemplate = Request::$isAdminEnv ? $this->templateHelper->getActiveAdminTemplate() : $this->templateHelper->getActiveFrontTemplate();

        $this->templateSymlinkDest = THELIA_WEB_DIR.'templates-assets';
        $this->moduleSymlinkDest = THELIA_WEB_DIR.'modules-assets';

        $this->packages['modules'] = new EncoreModuleAssetsPathPackage(
            $this->moduleSymlinkDest.DS,
            $kernelDebug,
            $cacheService
        );

        if ($this->activeTemplate->getAssetsPath()) {
            $this->packages['manifest'] = new Package(new JsonManifestVersionStrategy($this->activeTemplate->getAbsoluteAssetsPath().'/manifest.json'));
            $this->createSymlink($this->activeTemplate->getAbsoluteAssetsPath(), $this->templateSymlinkDest.DS.$this->activeTemplate->getPath().DS.$this->activeTemplate->getAssetsPath(), true);
        }
    }

    public function functionModuleAsset($args)
    {
        $file = $args['file'];
        $module = $args['module'];

        if (!$file) {
            return '';
        }

        $moduleAssetPath = $this->moduleSymlinkDest.DS.$module;

        if (
            !file_exists($moduleAssetPath.DS.pathinfo($file, \PATHINFO_DIRNAME))
            ||
            $this->kernelDebug
        ) {
            $this->symlinkModuleAssets($moduleAssetPath, $module);
        }

        try {
            return $this->packages['modules']->getUrl(DS.$module.DS.$file);
        } catch (\Throwable $th) {
            return '';
        }
    }

    public function getWebpackManifestFile($args): string
    {
        $file = $args['file'];
        if (!$file) {
            return '';
        }

        if (isset($this->packages['manifest'])) {
            return $this->packages['manifest']->geturl($file);
        }

        return '';
    }

    public function getWebpackJsFiles($args): array
    {
        $entryName = $args['entry'];

        return $this->entrypointLookupCollection->getEntrypointLookup($this->templateEnv)
            ->getJavaScriptFiles($entryName);
    }

    public function getWebpackCssFiles($args): array
    {
        $entryName = $args['entry'];

        return $this->entrypointLookupCollection->getEntrypointLookup($this->templateEnv)
            ->getCssFiles($entryName);
    }

    public function renderWebpackScriptTags($args): string
    {
        $entryName = $args['entry'];
        $packageName = $args['package'] ?? null;
        $entrypointName = $this->templateEnv;
        $attributes = $args['attributes'] ?? [];

        return $this->tagRenderer
            ->renderWebpackScriptTags($entryName, $packageName, $entrypointName, $attributes);
    }

    public function renderWebpackLinkTags($args): string
    {
        $entryName = $args['entry'];
        $packageName = $args['package'] ?? null;
        $entrypointName = $this->templateEnv;
        $attributes = $args['attributes'] ?? [];

        return $this->tagRenderer
            ->renderWebpackLinkTags($entryName, $packageName, $entrypointName, $attributes);
    }

    public function entryExists($args): bool
    {
        $entryName = $args['entry'];
        $entrypointName = $this->templateEnv;

        $entrypointLookup = $this->entrypointLookupCollection->getEntrypointLookup($entrypointName);

        return $entrypointLookup->entryExists($entryName);
    }

    /**
     * Define the various smarty plugins hendled by this class.
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'encore_module_asset', $this, 'functionModuleAsset'),
            new SmartyPluginDescriptor('function', 'encore_manifest_file', $this, 'getWebpackManifestFile'),
            new SmartyPluginDescriptor('function', 'encore_entry_js_files', $this, 'getWebpackJsFiles'),
            new SmartyPluginDescriptor('function', 'encore_entry_css_files', $this, 'getWebpackCssFiles'),
            new SmartyPluginDescriptor('function', 'encore_entry_script_tags', $this, 'renderWebpackScriptTags'),
            new SmartyPluginDescriptor('function', 'encore_entry_link_tags', $this, 'renderWebpackLinkTags'),
            new SmartyPluginDescriptor('function', 'encore_entry_exists', $this, 'entryExists'),
        ];
    }

    private function createSymlink($origin, $dest, $isDir = true, $forceOverWrite = false): void
    {
        $fileSystem = new Filesystem();

        if (
            $isDir && (!is_dir($origin) || (is_dir($dest) && !$forceOverWrite))
            ||
            !$isDir && (!file_exists($origin) || (file_exists($dest) && !$forceOverWrite))
        ) {
            return;
        }

        $fileSystem->symlink($origin, $dest);
    }

    private function symlinkModuleAssets($targetFolder, $module = null): void
    {
        $possiblePaths = array_merge(
            [
                THELIA_MODULE_DIR.$module.DS.'templates'.DS.$this->templateEnv.DS.'default',
                THELIA_MODULE_DIR.$module.DS.'templates'.DS.$this->activeTemplate->getPath(),
            ],
            // paths from template parents
            array_reverse(
                array_map(
                    function ($parent) use ($module) {
                        return $parent->getAbsolutePath().DS.'modules'.DS.$module;
                    },
                    array_values($this->activeTemplate->getParentList())
                )
            ),
            [
                $this->activeTemplate->getAbsolutePath().DS.'modules'.DS.$module,
            ]
        );

        foreach ($possiblePaths as $possiblePath) {
            if (!file_exists($possiblePath)) {
                continue;
            }
            $this->symlinkFolderFiles($possiblePath, $targetFolder);
        }
    }

    private function symlinkFolderFiles($folder, $targetFolder): void
    {
        $finder = new Finder();
        $finder->files()->in($folder);

        foreach ($finder as $file) {
            $this->createSymlink(
                $file->getPath().DS.$file->getFilename(),
                $targetFolder.DS.$file->getRelativePath().DS.$file->getFilename(),
                false,
                true
            );
        }
    }
}

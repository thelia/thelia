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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\Assets\EncoreTemplateAssetsPathPackage;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Encore extends AbstractSmartyPlugin
{
    /** @var array */
    public $packages;

    /** @var TemplateHelperInterface */
    public $templateHelper;

    /** @var TagRenderer */
    public $tagRenderer;

    /** @var EntrypointLookupCollectionInterface */
    public $entrypointLookupInterface;

    /** @var string */
    public $templateSymlinkOrigin;

    /** @var string */
    public $templateSymlinkDest;

    public function __construct(TagRenderer $tagRenderer, EntrypointLookupCollectionInterface $entrypointLookupInterface, TemplateHelperInterface $templateHelper)
    {
        $this->packages = [];
        $this->templateHelper = $templateHelper;
        $this->tagRenderer = $tagRenderer;
        $this->entrypointLookupInterface = $entrypointLookupInterface;
        $this->templateEnv = Request::$isAdminEnv ? TemplateDefinition::BACK_OFFICE_SUBDIR : TemplateDefinition::FRONT_OFFICE_SUBDIR;
        $this->activeTemplate = Request::$isAdminEnv ? $this->templateHelper->getActiveAdminTemplate() : $this->templateHelper->getActiveFrontTemplate();

        $this->templateSymlinkDest = THELIA_WEB_DIR.'assets';

        if ($this->activeTemplate->getTemplateAssetsPath()) {
            $this->packages['template-assets'] = new EncoreTemplateAssetsPathPackage($this->activeTemplate);
        }
        if ($this->activeTemplate->getCompiledAssetsPath()) {
            $this->packages['manifest'] = new Package(new JsonManifestVersionStrategy($this->activeTemplate->getAbsoluteCompiledAssetsPath().'/manifest.json'));
            $this->createSymlink($this->activeTemplate->getAbsoluteCompiledAssetsPath(), $this->templateSymlinkDest.DS.$this->activeTemplate->getPath().DS.$this->activeTemplate->getCompiledAssetsPath(), true);
        }
    }

    public function functionAsset($args)
    {
        $file = $args['file'];
        if (!$file) {
            return '';
        }

        if (isset($this->packages['template-assets'])) {
            return $this->packages['template-assets']->getUrl($file);
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
    }

    public function getWebpackJsFiles($args): array
    {
        $entryName = $args['entry'];

        return $this->entrypointLookupInterface->getEntrypointLookup($this->templateEnv)
            ->getJavaScriptFiles($entryName);
    }

    public function getWebpackCssFiles($args): array
    {
        $entryName = $args['entry'];

        return $this->entrypointLookupInterface->getEntrypointLookup($this->templateEnv)
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

        $entrypointLookup = $this->getEntrypointLookup->getEntrypointLookup($entrypointName);
        if (!$entrypointLookup instanceof EntrypointLookup) {
            throw new \LogicException(sprintf('Cannot use entryExists() unless the entrypoint lookup is an instance of "%s"', EntrypointLookup::class));
        }

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
            new SmartyPluginDescriptor('function', 'encore_asset', $this, 'functionAsset'),
            new SmartyPluginDescriptor('function', 'encore_manifest_file', $this, 'getWebpackManifestFile'),
            new SmartyPluginDescriptor('function', 'encore_entry_js_files', $this, 'getWebpackJsFiles'),
            new SmartyPluginDescriptor('function', 'encore_entry_css_files', $this, 'getWebpackCssFiles'),
            new SmartyPluginDescriptor('function', 'encore_entry_script_tags', $this, 'renderWebpackScriptTags'),
            new SmartyPluginDescriptor('function', 'encore_entry_link_tags', $this, 'renderWebpackLinkTags'),
            new SmartyPluginDescriptor('function', 'encore_entry_exists', $this, 'entryExists'),
        ];
    }

    private function createSymlink($origin, $dest, $isDir = true): void
    {
        $fileSystem = new Filesystem();

        if ($isDir && is_dir($origin)) {
            if (!is_dir($dest)) {
                $fileSystem->symlink($origin, $dest);
            }

            return;
        }

        if (!$isDir && file_exists($origin)) {
            if (!file_exists($dest)) {
                $fileSystem->symlink($origin, $dest);
            }

            return;
        }
    }

    private function findCorrectTemplate($file, $module = null, $template = null): string|null
    {
        $path = null;

        // local/modules/MyModule/templates/backOffice/custom-template/foo.css
        if ('local'.DS.'modules'.DS.$module.DS.'templates'.DS.$this->templateEnv.DS.$template.DS.$file) {
        }
        // local/modules/MyModule/templates/backOffice/default/foo.css
        elseif ('local'.DS.'modules'.DS.$module.DS.'templates'.DS.$this->templateEnv.DS.'default'.DS.$file) {
        }
        // templates/backOffice/custom-template/modules/MyModule/foo.css
        elseif ('templates'.DS.$this->templateEnv.DS.$template.DS.'modules'.DS.$module.DS.$file) {
            // TODO
        }
        // templates/backOffice/custom-template::parent/modules/MyModule/foo.css (handle template hierarchy)
        elseif ('templates'.DS.$this->templateEnv.DS.$template.DS.'modules'.DS.$module.DS.$file) {
            // TODO
        }
        // templates/backOffice/default/modules/MyModule/foo.css
        elseif ('templates'.DS.$this->templateEnv.DS.'default'.DS.'modules'.DS.$module.DS.$file) {
            // TODO
        }
        // templates/backOffice/custom-template/foo.css (handle template hierarchy)
        elseif ('templates'.DS.$this->templateEnv.DS.$template.DS.$file) {
        }
        // templates/backOffice/default/foo.css
        elseif ('templates'.DS.$this->templateEnv.DS.'default'.DS.$file) {
        }

        return $path;
    }
}

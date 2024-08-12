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

namespace TwigEngine\Extension;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Twig\Extension\AbstractExtension;

class EncoreExtension extends AbstractExtension
{
    public array $packages;

    public string $templateSymlinkDest;

    public TemplateDefinition $activeTemplate;

    public function __construct(
        private readonly TemplateHelperInterface $templateHelper,
    ) {
        $this->packages = [];
        $this->activeTemplate = Request::$isAdminEnv ? $this->templateHelper->getActiveAdminTemplate() : $this->templateHelper->getActiveFrontTemplate();

        $this->templateSymlinkDest = THELIA_WEB_DIR.'templates-assets';

        if ($this->activeTemplate->getAssetsPath()) {
            $this->packages['manifest'] = new Package(new JsonManifestVersionStrategy($this->activeTemplate->getAbsoluteAssetsPath().'/manifest.json'));
            $this->createSymlink($this->activeTemplate->getAbsoluteAssetsPath(), $this->templateSymlinkDest.DS.$this->activeTemplate->getPath().DS.$this->activeTemplate->getAssetsPath(), true);
        }
    }

    private function createSymlink($origin, $dest, $isDir = true, $forceOverWrite = false): void
    {
        $fileSystem = new Filesystem();

        if (
            $isDir && (!is_dir($origin) || (is_dir($dest) && !$forceOverWrite))
            || !$isDir && (!file_exists($origin) || (file_exists($dest) && !$forceOverWrite))
        ) {
            return;
        }

        $fileSystem->symlink($origin, $dest);
    }
}

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

use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Template\TemplateDefinition;

 class EncoreTemplateAssetsPathPackage extends PathPackage
 {
     public $symlinkOrigin;
     public $symlinkDestination;

     public function __construct(TemplateDefinition $template)
     {
         $this->symlinkOrigin = $template->getAbsoluteTemplateAssetsPath();
         $this->symlinkDestination = THELIA_WEB_ASSETS_DIR.$template->getPath().DS.$template->getTemplateAssetsPath();

         parent::__construct('assets'.DS.$template->getPath().DS.$template->getTemplateAssetsPath(), new EncoreTemplateAssetsVersionStrategy($template->getAbsoluteTemplateAssetsPath()));
     }

     public function getUrl(string $path): string
     {
         if (is_dir($this->symlinkOrigin) && !is_dir($this->symlinkDestination)) {
             $fs = new Filesystem();
             $fs->symlink($this->symlinkOrigin, $this->symlinkDestination);
         }

         $url = parent::getUrl($path);

         return $url;
     }
 }

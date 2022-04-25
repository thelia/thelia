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

use Symfony\Component\Asset\Package;

class EncoreModuleAssetsPathPackage extends Package
{
    public function __construct(string $path)
    {
        parent::__construct(new EncoreModuleAssetsVersionStrategy($path));
    }

    public function getUrl(string $path): string
    {
        $url = '/modules-assets'.parent::getUrl($path);

        return $url;
    }
}

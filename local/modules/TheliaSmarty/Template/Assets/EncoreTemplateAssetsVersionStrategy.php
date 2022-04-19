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

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class EncoreTemplateAssetsVersionStrategy implements VersionStrategyInterface
{
    private $originPath;

    public function __construct($originFSPath)
    {
        $this->originPath = $originFSPath;
    }

    public function getVersion(string $path): string
    {
        return md5_file($this->originPath.DS.$path);
    }

    public function applyVersion(string $path): string
    {
        return sprintf('%s?v=%s', $path, $this->getVersion($path));
    }
}

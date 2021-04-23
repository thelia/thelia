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

namespace Thelia\Tools;

class AssetsManager
{
    private static $instance;

    protected $processed = [];
    protected $entrypoints = [];
    protected $entrypointsPath = THELIA_FRONT_ASSETS_ENTRYPOINTS_PATH;

    protected function __construct()
    {
        if (file_exists($this->entrypointsPath)) {
            $json = json_decode(file_get_contents($this->entrypointsPath), true);
            $this->entrypoints = $json['entrypoints'];
        }
    }

    protected function __clone()
    {
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getAssets($entry, $type)
    {
        $assets = [];
        if (isset($this->entrypoints[$entry][$type])) {
            $assets = array_diff($this->entrypoints[$entry][$type], $this->processed);
            $this->processed = array_merge($this->processed, $assets);
        }

        return $assets;
    }
}

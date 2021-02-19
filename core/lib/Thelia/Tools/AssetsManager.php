<?php

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

    protected function __clone(){}

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new AssetsManager();
        }
        return self::$instance;
    }

    public function getAssets($entry, $type)
    {
        $assets = [];
        if ($this->entrypoints[$entry][$type]) {
            $assets = array_diff($this->entrypoints[$entry][$type], $this->processed);
            $this->processed = array_merge($this->processed, $assets);
        };
        return $assets;
    }
}
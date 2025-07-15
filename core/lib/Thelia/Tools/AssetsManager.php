<?php

declare(strict_types=1);

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
    private static ?AssetsManager $instance = null;
    protected $processed = [];
    protected $entrypoints = [];

    protected function __construct(protected $entrypointsPath)
    {
        if (null !== $this->entrypointsPath && file_exists($this->entrypointsPath)) {
            $json = json_decode(file_get_contents($this->entrypointsPath), true);
            $this->entrypoints = $json['entrypoints'];
        }
    }

    public static function getInstance($entrypointsPath): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($entrypointsPath);
        }

        return self::$instance;
    }

    /**
     * @return mixed[]
     */
    public function getAssets($entry, $type): array
    {
        $assets = [];

        if (isset($this->entrypoints[$entry][$type])) {
            $assets = array_diff($this->entrypoints[$entry][$type], $this->processed);
            $this->processed = array_merge($this->processed, $assets);
        }

        return $assets;
    }
}

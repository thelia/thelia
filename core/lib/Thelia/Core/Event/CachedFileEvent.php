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

namespace Thelia\Core\Event;

class CachedFileEvent extends ActionEvent
{
    /** @var string The complete file name (with path) of the source file */
    protected string $source_filepath;

    /** @var string The target subdirectory in the cache */
    protected string $cache_subdirectory;

    /** @var string The absolute URL of the cached file (in the web space) */
    protected string $file_url;

    /** @var string The absolute path of the cached file */
    protected string $cache_filepath;

    public function getFileUrl()
    {
        return $this->file_url;
    }

    public function setFileUrl($file_url): static
    {
        $this->file_url = $file_url;

        return $this;
    }

    public function getCacheFilepath()
    {
        return $this->cache_filepath;
    }

    public function setCacheFilepath($cache_filepath): static
    {
        $this->cache_filepath = $cache_filepath;

        return $this;
    }

    public function getSourceFilepath()
    {
        return $this->source_filepath;
    }

    public function setSourceFilepath($source_filepath): static
    {
        $this->source_filepath = $source_filepath;

        return $this;
    }

    public function getCacheSubdirectory()
    {
        return $this->cache_subdirectory;
    }

    public function setCacheSubdirectory($cache_subdirectory): static
    {
        $this->cache_subdirectory = $cache_subdirectory;

        return $this;
    }
}

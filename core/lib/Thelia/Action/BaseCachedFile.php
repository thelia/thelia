<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/
namespace Thelia\Action;

use Thelia\Core\Event\CachedFileEvent;
use Thelia\Tools\URL;

/**
 *
 * Cached file management actions. This class handles file caching in the web space
 *
 * Basically, files are stored outside the web space (by default in local/media/<dirname>),
 * and cached in the web space (by default in web/local/<dirname>).
 *
 * In the file cache directory, a subdirectory for files categories (eg. product, category, folder, etc.) is
 * automatically created, and the cached file is created here. Plugin may use their own subdirectory as required.
 *
 * A copy (or symbolic link, by default) of the original file is created in the cache.
 *
 * @package Thelia\Action
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
abstract class BaseCachedFile extends BaseAction
{
    /**
     * @return string root of the file cache directory in web space
     */
    abstract protected function getCacheDirFromWebRoot();

    /**
     * Clear the file cache. Is a subdirectory is specified, only this directory is cleared.
     * If no directory is specified, the whole cache is cleared.
     * Only files are deleted, directories will remain.
     *
     * @param CachedFileEvent $event
     */
    public function clearCache(CachedFileEvent $event)
    {
        $path = $this->getCachePath($event->getCacheSubdirectory(), false);

        $this->clearDirectory($path);
    }

    /**
     * Recursively clears the specified directory.
     *
     * @param string $path the directory path
     */
    protected function clearDirectory($path)
    {
        $iterator = new \DirectoryIterator($path);

        foreach ($iterator as $fileinfo) {

            if ($fileinfo->isDot()) continue;

            if ($fileinfo->isFile() || $fileinfo->isLink()) {
                @unlink($fileinfo->getPathname());
            } elseif ($fileinfo->isDir()) {
                $this->clearDirectory($fileinfo->getPathname());
            }
        }
    }

    /**
     * Return the absolute URL to the cached file
     *
     * @param  string $subdir   the subdirectory related to cache base
     * @param  string $filename the safe filename, as returned by getCacheFilePath()
     * @return string the absolute URL to the cached file
     */
    protected function getCacheFileURL($subdir, $safe_filename)
    {
        $path = $this->getCachePathFromWebRoot($subdir);

        return URL::getInstance()->absoluteUrl(sprintf("%s/%s", $path, $safe_filename), null, URL::PATH_TO_FILE);
    }

    /**
     * Return the full path of the cached file
     *
     * @param  string  $subdir                the subdirectory related to cache base
     * @param  string  $filename              the filename
     * @param  string  $hashed_options        a hash of transformation options, or null if no transformations have been applied
     * @param  boolean $forceOriginalDocument if true, the original file path in the cache dir is returned.
     * @return string  the cache directory path relative to Web Root
     */
    protected function getCacheFilePath($subdir, $filename, $forceOriginalFile = false, $hashed_options = null)
    {
        $path = $this->getCachePath($subdir);

        $safe_filename = preg_replace("[^:alnum:\-\._]", "-", strtolower(basename($filename)));

       // Keep original safe name if no tranformations are applied
       if ($forceOriginalFile || $hashed_options == null)
            return sprintf("%s/%s", $path, $safe_filename);
        else
            return sprintf("%s/%s-%s", $path, $hashed_options, $safe_filename);
    }

    /**
     * Return the cache directory path relative to Web Root
     *
     * @param  string $subdir the subdirectory related to cache base, or null to get the cache directory only.
     * @return string the cache directory path relative to Web Root
     */
    protected function getCachePathFromWebRoot($subdir = null)
    {
        $cache_dir_from_web_root = $this->getCacheDirFromWebRoot();

        if ($subdir != null) {
            $safe_subdir = basename($subdir);

            $path = sprintf("%s/%s", $cache_dir_from_web_root, $safe_subdir);
        } else
            $path = $cache_dir_from_web_root;

        // Check if path is valid, e.g. in the cache dir
        return $path;
    }

    /**
     * Return the absolute cache directory path
     *
     * @param  string            $subdir the subdirectory related to cache base, or null to get the cache base directory.
     * @throws \RuntimeException if cache directory cannot be created
     * @return string            the absolute cache directory path
     */
    protected function getCachePath($subdir = null, $create_if_not_exists = true)
    {
        $cache_base = $this->getCachePathFromWebRoot($subdir);

        $web_root = rtrim(THELIA_WEB_DIR, '/');

        $path = sprintf("%s/%s", $web_root, $cache_base);

        // Create directory (recursively) if it does not exists.
        if ($create_if_not_exists && !is_dir($path)) {
            if (!@mkdir($path, 0777, true)) {
                throw new \RuntimeException(sprintf("Failed to create %s file in cache directory",  $path));
            }
        }

        // Check if path is valid, e.g. in the cache dir
        $cache_base = realpath(sprintf("%s/%s", $web_root, $this->getCachePathFromWebRoot()));

        if (strpos(realpath($path), $cache_base) !== 0) {
            throw new \InvalidArgumentException(sprintf("Invalid cache path %s, with subdirectory %s", $path, $subdir));
        }

        return $path;
    }
}

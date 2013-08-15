<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\ImageEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

use Imagine\Image\ImagineInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Color;
use Imagine\Image\Point;
use Thelia\Exception\ImageException;
use Thelia\Log\Tlog;
use Thelia\Core\Event\TheliaEvents;

/**
 *
 * Image management actions
 *
 * @package Thelia\Action
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class Image extends BaseAction implements EventSubscriberInterface
{
    // Resize mode constants
    const EXACT_RATIO_WITH_BORDERS = 1;
    const EXACT_RATIO_WITH_CROP = 2;
    const KEEP_IMAGE_RATIO = 3;

    /**
     * Process image and write the result in the image cache.
     *
     * If the image already exists in cache, the cache file is immediately returned, without any processing
     * If the original (full resolution) image is required, create either a symbolic link with the
     * original image in the cache dir, or copy it in the cache dir.
     *
     * This method updates the cache_file_path and file_url attributes of the event
     *
     * @param ImageEvent $event
     * @throws \InvalidArgumentException, ImageException
     */
    public function processImage(ImageEvent $event)
    {

        $subdir      = $event->getCacheSubdirectory();
        $source_file = $event->getSourceFilepath();

        if (null == $subdir || null == $source_file) {
            throw new \InvalidArgumentException("Cache sub-directory and source file path cannot be null");
        }

        // echo basename($source_file).": ";

        // Find cached file path
        $cacheFilePath = $this->getCacheFilePath($subdir, $source_file, $event);

        $originalImagePathInCache = $this->getCacheFilePath($subdir, $source_file, $event, true);

        if (! file_exists($cacheFilePath)) {

            // Create a chached version of the original image in the web space, if not exists

            if (! file_exists($originalImagePathInCache)) {
                $mode = ConfigQuery::read('original_image_delivery_mode', 'symlink');

                if ($mode == 'symlink') {
                    if (false == @symlink($source_file, $originalImagePathInCache)) {
                        $error_message = sprintf("Failed to create symbolic link for %s in %s image cache directory", basename($source_file), $subdir);
                        Tlog::getInstance()->addError($error_message);
                        throw new ImageException($error_message);
                    }
                }
                else {// mode = 'copy'
                    if (false == @copy($source_file, $originalImagePathInCache)) {
                        $error_message = sprintf("Failed to copy %s in %s image cache directory", basename($source_file), $subdir);
                        Tlog::getInstance()->addError($error_message);
                        throw new ImageException($error_message);
                    }
                }
            }

            // Process image only if we have some transformations to do.
            if (! $event->isOriginalImage()) {

                // We have to process the image.
                $imagine = $this->createImagineInstance();

                $image = $imagine->open($source_file);

                if ($image) {

                    $background_color = $event->getBackgroundColor();

                    if ($background_color != null) {
                        $bg_color = new Color($background_color);
                    }
                    else
                        $bg_color = null;

                    // Apply resize
                    $image = $this->applyResize($imagine, $image, $event->getWidth(), $event->getHeight(), $event->getResizeMode(), $bg_color);

                    // Rotate if required
                    $rotation = intval($event->getRotation());

                    if ($rotation != 0)
                        $image->rotate($rotation, $bg_color);

                    // Flip
                    // Process each effects
                    foreach ($event->getEffects() as $effect) {

                        $effect = trim(strtolower($effect));

                        $params = explode(':', $effect);

                        switch ($params[0]) {

                        case 'greyscale':
                        case 'grayscale':
                            $image->effects()->grayscale();
                            break;

                        case 'negative':
                            $image->effects()->negative();
                            break;

                        case 'horizontal_flip':
                        case 'hflip':
                            $image->flipHorizontally();
                            break;

                        case 'vertical_flip':
                        case 'vflip':
                            $image-> flipVertically();
                            break;

                        case 'gamma':
                            // Syntax: gamma:value. Exemple: gamma:0.7
                            if (isset($params[1])) {
                                $gamma = floatval($params[1]);

                                $image->effects()->gamma($gamma);
                            }
                            break;

                        case 'colorize':
                            // Syntax: colorize:couleur. Exemple: colorize:#ff00cc
                            if (isset($params[1])) {
                                $the_color = new Color($params[1]);

                                $image->effects()->colorize($the_color);
                            }
                            break;
                        }
                    }

                    $quality = $event->getQuality();

                    if (is_null($quality)) $quality = ConfigQuery::read('default_image_quality_percent', 75);

                    $image->save(
                            $cacheFilePath,
                            array('quality' => $quality)
                     );
                }
            }
        }
        else {
            throw new ImageException(sprintf("Source file %s cannot be opened.", basename($source_file)));
        }

        // Compute the image URL
        $processed_image_url = $this->getCacheFileURL($subdir, basename($cacheFilePath));

        // compute the full resulution image path in cache
        $original_image_url = $this->getCacheFileURL($subdir, basename($originalImagePathInCache));

        // Update the event with file path and file URL
        $event->setCacheFilepath($cacheFilePath);
        $event->setCacheOriginalFilepath($originalImagePathInCache);

        $event->setFileUrl(URL::absoluteUrl($processed_image_url));
        $event->setOriginalFileUrl(URL::absoluteUrl($original_image_url));
    }

    /**
     * Process image resizing, with borders or cropping. If $dest_width and $dest_height
     * are both null, no resize is performed.
     *
     * @param ImagineInterface $imagine the Imagine instance
     * @param ImageInterface $image the image to process
     * @param int $dest_width the required width
     * @param int $dest_height the required height
     * @param int $resize_mode the resize mode (crop / bands / keep image ratio)p
     * @param string $bg_color the bg_color used for bands
     * @return ImageInterface the resized image.
     */
    protected function applyResize(ImagineInterface $imagine, ImageInterface $image, $dest_width, $dest_height, $resize_mode, $bg_color)
    {
        if (! (is_null($dest_width) && is_null($dest_height))) {

            $width_orig = $image->getSize()->getWidth();
            $height_orig = $image->getSize()->getHeight();

            if (is_null($dest_width))
                $dest_width = $width_orig;

            if (is_null($dest_height))
                $dest_height = $height_orig;

            if (is_null($resize_mode))
                $resize_mode = self::KEEP_IMAGE_RATIO;

            $width_diff = $dest_width / $width_orig;
            $height_diff = $dest_height / $height_orig;

            $delta_x = $delta_y = $border_width = $border_height = 0;

            if ($width_diff > 1 AND $height_diff > 1) {

                $next_width = $width_orig;
                $next_height = $height_orig;

                $dest_width = ($resize_mode == self::EXACT_RATIO_WITH_BORDERS ? $dest_width : $next_width);
                $dest_height = ($resize_mode == self::EXACT_RATIO_WITH_BORDERS ? $dest_height : $next_height);
            }
            else if ($width_diff > $height_diff) {
                // Image height > image width

                $next_height = $dest_height;
                $next_width = intval(($width_orig * $next_height) / $height_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $next_width = $dest_width;
                    $next_height = intval($height_orig * $dest_width / $width_orig);
                    $delta_y = ($next_height - $dest_height) / 2;
                }
                else if ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_width = $next_width;
                }
            }
            else {
                // Image width > image height
                $next_width = $dest_width;
                $next_height = intval($height_orig * $dest_width / $width_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $next_height = $dest_height;
                    $next_width  = intval(($width_orig * $next_height) / $height_orig);
                    $delta_x = ($next_width - $dest_width) / 2;
                }
                else if ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_height = $next_height;
                }
            }

            $image->resize(new Box($next_width, $next_height));

            // echo "w=$dest_width, h=$dest_height, nw=$next_width, nh=$next_height, dx=$delta_x, dy=$delta_y, bw=$border_width, bh=$border_height\n";

            if ($resize_mode == self::EXACT_RATIO_WITH_BORDERS) {

                $border_width = intval(($dest_width - $next_width) / 2);
                $border_height = intval(($dest_height - $next_height) / 2);

                $canvas = new Box($dest_width, $dest_height);

                return $imagine->create($canvas, $bg_color)
                    ->paste($image, new Point($border_width, $border_height));
            }

            else if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                $image->crop(
                    new Point($delta_x, $delta_y),
                    new Box($dest_width, $dest_height)
                );
            }
        }

        return $image;
    }


    /**
     * Return the absolute URL to the cached image
     *
     * @param string $subdir the subdirectory related to cache base
     * @param string $filename the safe filename, as returned by getCacheFilePath()
     * @return string the absolute URL to the cached image
     */
    protected function getCacheFileURL($subdir, $safe_filename)
    {
        $path = $this->getCachePathFromWebRoot($subdir);

        return URL::absoluteUrl(sprintf("%s/%s", $path, $safe_filename));
    }

    /**
     * Return the full path of the cached file
     *
     * @param string $subdir the subdirectory related to cache base
     * @param string $filename the filename
     * @param boolean $forceOriginalImage if true, the origiunal image path in the cache dir is returned.
     * @return string the cache directory path relative to Web Root
     */
    protected function getCacheFilePath($subdir, $filename, ImageEvent $event, $forceOriginalImage = false)
    {
        $path = $this->getCachePath($subdir);

        $safe_filename = preg_replace("[^:alnum:\-\._]", "-", strtolower(basename($filename)));

       // Keep original safe name if no tranformations are applied
       if ($forceOriginalImage || $event->isOriginalImage())
            return sprintf("%s/%s", $path, $safe_filename);
        else
            return sprintf("%s/%s-%s", $path, $event->getSignature(), $safe_filename);
    }

    /**
     * Return the cache directory path relative to Web Root
     *
     * @param string $subdir the subdirectory related to cache base
     * @return string the cache directory path relative to Web Root
     */
    protected function getCachePathFromWebRoot($subdir)
    {
        $safe_subdir = basename($subdir);

        $cache_dir_from_web_root = ConfigQuery::read('image_cache_dir_from_web_root', 'cache');

        $path = sprintf("%s/%s", $cache_dir_from_web_root, $safe_subdir);

        return $path;
    }

    /**
     * Return the absolute cache directory path
     *
     * @param string $subdir the subdirectory related to cache base
     * @throws \RuntimeException if cache directory cannot be created
     * @return string the absolute cache directory path
     */
    protected function getCachePath($subdir)
    {
        $cache_base = $this->getCachePathFromWebRoot($subdir);

        $web_root = rtrim(THELIA_WEB_DIR, '/');

        $path = sprintf("%s/%s", $web_root, $cache_base);

        // Create directory (recursively) if it does not exists.
        if (!is_dir($path)) {
            if (!@mkdir($path, 0777, true)) {
                $error_message = sprintf("Failed to create %s/%s image cache directory",  $cache_base);
                Tlog::getInstance()->addError($error_message);

                throw new ImageException($error_message);
            }
        }

        return $path;
    }

    /**
     * Create a new Imagine object using current driver configuration
     *
     * @return \Imagine\ImagineInterface
     */
    protected function createImagineInstance()
    {
        $driver = ConfigQuery::read("imagine_driver", "gd");

        switch ($driver) {
        case 'imagik':
            $image = new \Imagine\Imagick\Imagine();
            break;

        case 'gmagick':
            $image = new \Imagine\Gmagick\Imagine();
            break;

        case 'gd':
        default:
            $image = new \Imagine\Gd\Imagine();
        }

        return $image;
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::IMAGE_PROCESS => array(
                "processImage", 128
            ),
        );
    }
}

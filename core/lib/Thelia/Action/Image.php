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

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\ImageException;
use Thelia\Files\FileManager;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use Imagine\Imagick\Imagine as ImagickImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Gd\Imagine;

/**
 *
 * Image management actions. This class handles image processing and caching.
 *
 * Basically, images are stored outside of the web space (by default in local/media/images),
 * and cached inside the web space (by default in web/local/images).
 *
 * In the images caches directory, a subdirectory for images categories (eg. product, category, folder, etc.) is
 * automatically created, and the cached image is created here. Plugin may use their own subdirectory as required.
 *
 * The cached image name contains a hash of the processing options, and the original (normalized) name of the image.
 *
 * A copy (or symbolic link, by default) of the original image is always created in the cache, so that the full
 * resolution image is always available.
 *
 * Various image processing options are available :
 *
 * - resizing, with border, crop, or by keeping image aspect ratio
 * - rotation, in degrees, positive or negative
 * - background color, applyed to empty background when creating borders or rotating
 * - effects. The effects are applied in the specified order. The following effects are available:
 *    - gamma:value : change the image Gamma to the specified value. Example: gamma:0.7
 *    - grayscale or greyscale: switch image to grayscale
 *    - colorize:color : apply a color mask to the image. Exemple: colorize:#ff2244
 *    - negative : transform the image in its negative equivalent
 *    - vflip or vertical_flip : vertical flip
 *    - hflip or horizontal_flip : horizontal flip
 *
 * If a problem occurs, an ImageException may be thrown.
 *
 * @package Thelia\Action
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class Image extends BaseCachedFile implements EventSubscriberInterface
{
    // Resize mode constants
    const EXACT_RATIO_WITH_BORDERS = 1;
    const EXACT_RATIO_WITH_CROP = 2;
    const KEEP_IMAGE_RATIO = 3;

    /**
     * @return string root of the image cache directory in web space
     */
    protected function getCacheDirFromWebRoot()
    {
        return ConfigQuery::read('image_cache_dir_from_web_root', 'cache' . DS . 'images');
    }

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
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     *
     * @throws \Thelia\Exception\ImageException
     * @throws \InvalidArgumentException
     */
    public function processImage(ImageEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $subdir      = $event->getCacheSubdirectory();
        $source_file = $event->getSourceFilepath();

        if (null == $subdir || null == $source_file) {
            throw new \InvalidArgumentException("Cache sub-directory and source file path cannot be null");
        }

        // Find cached file path
        $cacheFilePath = $this->getCacheFilePath($subdir, $source_file, $event->isOriginalImage(), $event->getOptionsHash());

        $originalImagePathInCache = $this->getCacheFilePath($subdir, $source_file, true);

        if (! file_exists($cacheFilePath)) {
            if (! file_exists($source_file)) {
                throw new ImageException(sprintf("Source image file %s does not exists.", $source_file));
            }

            // Create a cached version of the original image in the web space, if not exists

            if (! file_exists($originalImagePathInCache)) {
                $mode = ConfigQuery::read('original_image_delivery_mode', 'symlink');

                if ($mode == 'symlink') {
                    if (false == symlink($source_file, $originalImagePathInCache)) {
                        throw new ImageException(sprintf("Failed to create symbolic link for %s in %s image cache directory", basename($source_file), $subdir));
                    }
                } else {
                    // mode = 'copy'
                    if (false == @copy($source_file, $originalImagePathInCache)) {
                        throw new ImageException(sprintf("Failed to copy %s in %s image cache directory", basename($source_file), $subdir));
                    }
                }
            }

            // Process image only if we have some transformations to do.
            if (! $event->isOriginalImage()) {
                // We have to process the image.
                $imagine = $this->createImagineInstance();

                $image = $imagine->open($source_file);

                if ($image) {
                    // Allow image pre-processing (watermarging, or other stuff...)
                    $event->setImageObject($image);
                    $dispatcher->dispatch(TheliaEvents::IMAGE_PREPROCESSING, $event);
                    $image = $event->getImageObject();

                    $background_color = $event->getBackgroundColor();

                    $palette = new RGB();

                    if ($background_color != null) {
                        $bg_color = $palette->color($background_color);
                    } else {
                        // Define a fully transparent white background color
                        $bg_color = $palette->color('fff', 0);
                    }

                    // Apply resize
                    $image = $this->applyResize(
                        $imagine,
                        $image,
                        $event->getWidth(),
                        $event->getHeight(),
                        $event->getResizeMode(),
                        $bg_color,
                        $event->getAllowZoom()
                    );

                    // Rotate if required
                    $rotation = intval($event->getRotation());

                    if ($rotation != 0) {
                        $image->rotate($rotation, $bg_color);
                    }

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
                                $image->flipVertically();
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
                                    $the_color = $palette->color($params[1]);

                                    $image->effects()->colorize($the_color);
                                }
                                break;
                        }
                    }

                    $quality = $event->getQuality();

                    if (is_null($quality)) {
                        $quality = ConfigQuery::read('default_images_quality_percent', 75);
                    }

                    // Allow image post-processing (watermarging, or other stuff...)
                    $event->setImageObject($image);
                    $dispatcher->dispatch(TheliaEvents::IMAGE_POSTPROCESSING, $event);
                    $image = $event->getImageObject();

                    $image->save(
                        $cacheFilePath,
                        array('quality' => $quality)
                    );
                } else {
                    throw new ImageException(sprintf("Source file %s cannot be opened.", basename($source_file)));
                }
            }
        }

        // Compute the image URL
        $processed_image_url = $this->getCacheFileURL($subdir, basename($cacheFilePath));

        // compute the full resolution image path in cache
        $original_image_url = $this->getCacheFileURL($subdir, basename($originalImagePathInCache));

        // Update the event with file path and file URL
        $event->setCacheFilepath($cacheFilePath);
        $event->setCacheOriginalFilepath($originalImagePathInCache);

        $event->setFileUrl(URL::getInstance()->absoluteUrl($processed_image_url, null, URL::PATH_TO_FILE));
        $event->setOriginalFileUrl(URL::getInstance()->absoluteUrl($original_image_url, null, URL::PATH_TO_FILE));
    }

    /**
     * Process image resizing, with borders or cropping. If $dest_width and $dest_height
     * are both null, no resize is performed.
     *
     * @param  ImagineInterface $imagine     the Imagine instance
     * @param  ImageInterface   $image       the image to process
     * @param  int              $dest_width  the required width
     * @param  int              $dest_height the required height
     * @param  int              $resize_mode the resize mode (crop / bands / keep image ratio)p
     * @param  string           $bg_color    the bg_color used for bands
     * @param  bool             $allow_zoom  if true, image may be zoomed to matchrequired size. If false, image is not zoomed.
     * @return ImageInterface   the resized image.
     */
    protected function applyResize(
        ImagineInterface $imagine,
        ImageInterface $image,
        $dest_width,
        $dest_height,
        $resize_mode,
        $bg_color,
        $allow_zoom = false
    ) {
        if (! (is_null($dest_width) && is_null($dest_height))) {
            $width_orig = $image->getSize()->getWidth();
            $height_orig = $image->getSize()->getHeight();

            $ratio = $width_orig / $height_orig;

            if (is_null($dest_width)) {
                $dest_width = $dest_height * $ratio;
            }

            if (is_null($dest_height)) {
                $dest_height = $dest_width / $ratio;
            }

            if (is_null($resize_mode)) {
                $resize_mode = self::KEEP_IMAGE_RATIO;
            }

            $width_diff = $dest_width / $width_orig;
            $height_diff = $dest_height / $height_orig;

            $delta_x = $delta_y = $border_width = $border_height = 0;

            if ($width_diff > 1 && $height_diff > 1) {
                $resize_width = $width_orig;
                $resize_height = $height_orig;

                // When cropping, be sure to always generate an image which is
                //  no smaller than the required size, zooming it if required.
                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    if ($allow_zoom) {
                        if ($width_diff > $height_diff) {
                            $resize_width = $dest_width;
                            $resize_height = intval($height_orig * $dest_width / $width_orig);
                            $delta_y = ($resize_height - $dest_height) / 2;
                        } else {
                            $resize_height = $dest_height;
                            $resize_width = intval(($width_orig * $resize_height) / $height_orig);
                            $delta_x = ($resize_width - $dest_width) / 2;
                        }
                    } else {
                        // No zoom : final image may be smaller than the required size.
                        $dest_width = $resize_width;
                        $dest_height = $resize_height;
                    }
                }
            } elseif ($width_diff > $height_diff) {
                // Image height > image width
                $resize_height = $dest_height;
                $resize_width = intval(($width_orig * $resize_height) / $height_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $resize_width = $dest_width;
                    $resize_height = intval($height_orig * $dest_width / $width_orig);
                    $delta_y = ($resize_height - $dest_height) / 2;
                } elseif ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_width = $resize_width;
                }
            } else {
                // Image width > image height
                $resize_width = $dest_width;
                $resize_height = intval($height_orig * $dest_width / $width_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $resize_height = $dest_height;
                    $resize_width  = intval(($width_orig * $resize_height) / $height_orig);
                    $delta_x = ($resize_width - $dest_width) / 2;
                } elseif ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_height = $resize_height;
                }
            }

            $image->resize(new Box($resize_width, $resize_height));

            if ($resize_mode == self::EXACT_RATIO_WITH_BORDERS) {
                $border_width = intval(($dest_width - $resize_width) / 2);
                $border_height = intval(($dest_height - $resize_height) / 2);

                $canvas = new Box($dest_width, $dest_height);

                return $imagine->create($canvas, $bg_color)
                    ->paste($image, new Point($border_width, $border_height));
            } elseif ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                $image->crop(
                    new Point($delta_x, $delta_y),
                    new Box($dest_width, $dest_height)
                );
            }
        }

        return $image;
    }

    /**
     * Create a new Imagine object using current driver configuration
     *
     * @return ImagineInterface
     */
    protected function createImagineInstance()
    {
        $driver = ConfigQuery::read("imagine_graphic_driver", "gd");

        switch ($driver) {
            case 'imagick':
                $image = new ImagickImagine();
                break;

            case 'gmagick':
                $image = new GmagickImagine();
                break;

            case 'gd':
            default:
                $image = new Imagine();
        }

        return $image;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::IMAGE_PROCESS => array("processImage", 128),

            // Implemented in parent class BaseCachedFile
            TheliaEvents::IMAGE_CLEAR_CACHE => array("clearCache", 128),
            TheliaEvents::IMAGE_DELETE => array("deleteFile", 128),
            TheliaEvents::IMAGE_SAVE => array("saveFile", 128),
            TheliaEvents::IMAGE_UPDATE => array("updateFile", 128),
            TheliaEvents::IMAGE_UPDATE_POSITION => array("updatePosition", 128),
            TheliaEvents::IMAGE_TOGGLE_VISIBILITY => array("toggleVisibility", 128),
        );
    }
}

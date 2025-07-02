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
namespace Thelia\Action;

use InvalidArgumentException;
use DOMDocument;
use Exception;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Gd\Imagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine as ImagickImagine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\ImageException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
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
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Image extends BaseCachedFile implements EventSubscriberInterface
{
    // Resize mode constants
    public const EXACT_RATIO_WITH_BORDERS = 1;

    public const EXACT_RATIO_WITH_CROP = 2;

    public const KEEP_IMAGE_RATIO = 3;

    /**
     * @return string root of the image cache directory in web space
     */
    protected function getCacheDirFromWebRoot()
    {
        return ConfigQuery::read('image_cache_dir_from_web_root', 'cache'.DS.'images');
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
     * @param string $eventName
     *
     * @throws ImageException
     * @throws InvalidArgumentException
     */
    public function processImage(ImageEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $subdir = $event->getCacheSubdirectory();
        $sourceFile = $event->getSourceFilepath();

        $imageExt = pathinfo((string) $sourceFile, \PATHINFO_EXTENSION);

        if (null == $subdir || null == $sourceFile) {
            throw new InvalidArgumentException('Cache sub-directory and source file path cannot be null');
        }

        // Find cached file path
        $cacheFilePath = $this->getCacheFilePath($subdir, $sourceFile, $event->isOriginalImage(), $event->getOptionsHash());

        // Alternative image path is for browser that don't support webp
        $alternativeImagePath = null;

        if ($event->getFormat()) {
            $sourceExtension = pathinfo($cacheFilePath, \PATHINFO_EXTENSION);
            if ($event->getFormat() === 'webp') {
                $alternativeImagePath = $cacheFilePath;
            }

            $cacheFilePath = str_replace($sourceExtension, $event->getFormat(), $cacheFilePath);
        }

        $originalImagePathInCache = $this->getCacheFilePath($subdir, $sourceFile, true);

        if (!file_exists($cacheFilePath)) {
            if (!file_exists($sourceFile)) {
                throw new ImageException(sprintf('Source image file %s does not exists.', $sourceFile));
            }

            // Create a cached version of the original image in the web space, if not exists

            if (!file_exists($originalImagePathInCache)) {
                $mode = ConfigQuery::read('original_image_delivery_mode', 'symlink');

                if ($mode == 'symlink') {
                    if (false === symlink($sourceFile, $originalImagePathInCache)) {
                        throw new ImageException(sprintf('Failed to create symbolic link for %s in %s image cache directory', basename((string) $sourceFile), $subdir));
                    }
                } elseif (false === @copy($sourceFile, $originalImagePathInCache)) {
                    // mode = 'copy'
                    throw new ImageException(sprintf('Failed to copy %s in %s image cache directory', basename((string) $sourceFile), $subdir));
                }
            }

            // Process image only if we have some transformations to do.
            if (!$event->isOriginalImage()) {
                if ('svg' === $imageExt) {
                    $dom = new DOMDocument('1.0', 'utf-8');
                    $dom->load($originalImagePathInCache);
                    $svg = $dom->documentElement;

                    if (!$svg->hasAttribute('viewBox')) {
                        $pattern = '/^(\d*\.\d+|\d+)(px)?$/';

                        $interpretable = preg_match($pattern, $svg->getAttribute('width'), $width)
                            && preg_match($pattern, $svg->getAttribute('height'), $height);

                        if (!$interpretable || !isset($width) || !isset($height)) {
                            throw new Exception("can't create viewBox if height and width is not defined in the svg file");
                        }

                        $viewBox = implode(' ', [0, 0, $width[0], $height[0]]);
                        $svg->setAttribute('viewBox', $viewBox);
                    }

                    $svg->setAttribute('width', $event->getWidth());
                    $svg->setAttribute('height', $event->getWidth());
                    $dom->save($cacheFilePath);
                } else {
                    $this->applyTransformation($sourceFile, $event, $dispatcher, $cacheFilePath);
                    if ($alternativeImagePath) {
                        $this->applyTransformation($sourceFile, $event, $dispatcher, $alternativeImagePath);
                    }
                }
            }
        }

        // Compute the image URL
        $processedImageUrl = $this->getCacheFileURL($subdir, basename($cacheFilePath));

        // compute the full resolution image path in cache
        $originalImageUrl = $this->getCacheFileURL($subdir, basename($originalImagePathInCache));

        // Update the event with file path and file URL
        $event->setCacheFilepath($cacheFilePath);
        $event->setCacheOriginalFilepath($originalImagePathInCache);

        $event->setFileUrl(URL::getInstance()->absoluteUrl($processedImageUrl, null, URL::PATH_TO_FILE, $this->cdnBaseUrl));
        $event->setOriginalFileUrl(URL::getInstance()->absoluteUrl($originalImageUrl, null, URL::PATH_TO_FILE, $this->cdnBaseUrl));

        $imagine = $this->createImagineInstance();
        $image = $imagine->open($cacheFilePath);
        $event->setImageObject($image);
    }

    private function applyTransformation(
        $sourceFile,
        ImageEvent $event,
        EventDispatcherInterface $dispatcher,
        $cacheFilePath
    ): void {
        $imagine = $this->createImagineInstance();
        $image = $imagine->open($sourceFile);

        if (!$image) {
            throw new ImageException(sprintf('Source file %s cannot be opened.', basename((string) $sourceFile)));
        }

        if (\function_exists('exif_read_data')) {
            $exifdata = @exif_read_data($sourceFile);
            if (isset($exifdata['Orientation'])) {
                $orientation = $exifdata['Orientation'];
                $color = new RGB();
                switch ($orientation) {
                    case 3:
                        $image->rotate(180, $color->color('#F00'));
                        break;

                    case 6:
                        $image->rotate(90, $color->color('#F00'));
                        break;

                    case 8:
                        $image->rotate(-90, $color->color('#F00'));
                        break;
                }
            }
        }

        // Allow image pre-processing (watermarging, or other stuff...)
        $event->setImageObject($image);
        $dispatcher->dispatch($event, TheliaEvents::IMAGE_PREPROCESSING);
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
        $rotation = (int) $event->getRotation();

        if ($rotation != 0) {
            $image->rotate($rotation, $bg_color);
        }

        // Flip
        // Process each effects
        foreach ($event->getEffects() as $effect) {
            $effect = trim(strtolower((string) $effect));

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
                        $gamma = (float) $params[1];

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
                case 'blur':
                    if (isset($params[1])) {
                        $blur_level = (int) $params[1];

                        $image->effects()->blur($blur_level);
                    }

                    break;
            }
        }

        $quality = $event->getQuality();

        if (null === $quality) {
            $quality = ConfigQuery::read('default_images_quality_percent', 75);
        }

        // Allow image post-processing (watermarging, or other stuff...)
        $event->setImageObject($image);
        $dispatcher->dispatch($event, TheliaEvents::IMAGE_POSTPROCESSING);
        $image = $event->getImageObject();

        $image->save(
            $cacheFilePath,
            ['quality' => $quality, 'animated' => true]
        );
    }

    /**
     * Process image resizing, with borders or cropping. If $dest_width and $dest_height
     * are both null, no resize is performed.
     *
     * @param ImagineInterface $imagine     the Imagine instance
     * @param ImageInterface   $image       the image to process
     * @param int              $dest_width  the required width
     * @param int              $dest_height the required height
     * @param int              $resize_mode the resize mode (crop / bands / keep image ratio)p
     * @param string           $bg_color    the bg_color used for bands
     * @param bool             $allow_zoom  if true, image may be zoomed to matchrequired size. If false, image is not zoomed.
     *
     * @return ImageInterface the resized image
     */
    protected function applyResize(
        ImagineInterface $imagine,
        ImageInterface $image,
        $dest_width,
        $dest_height,
        $resize_mode,
        ?ColorInterface $bg_color,
        $allow_zoom = false
    ) {
        if (!(null === $dest_width && null === $dest_height)) {
            $width_orig = $image->getSize()->getWidth();
            $height_orig = $image->getSize()->getHeight();

            $ratio = $width_orig / $height_orig;

            if (null === $dest_width) {
                $dest_width = $dest_height * $ratio;
            }

            if (null === $dest_height) {
                $dest_height = $dest_width / $ratio;
            }

            if (null === $resize_mode) {
                $resize_mode = self::KEEP_IMAGE_RATIO;
            }

            $width_diff = $dest_width / $width_orig;
            $height_diff = $dest_height / $height_orig;
            $delta_x = 0;
            $delta_y = 0;
            $border_width = 0;
            $border_height = 0;

            if ($width_diff > 1 && $height_diff > 1) {
                // Set the default final size. If zoom is allowed, we will get the required
                // image dimension. Otherwise, the final image may be smaller than required.
                if ($allow_zoom) {
                    $resize_width = $dest_width;
                    $resize_height = $dest_height;
                } else {
                    $resize_width = $width_orig;
                    $resize_height = $height_orig;
                }

                // When cropping, be sure to always generate an image which is
                // not smaller than the required size, zooming it if required.
                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    if ($allow_zoom) {
                        if ($width_diff > $height_diff) {
                            $resize_width = $dest_width;
                            $resize_height = (int) ($height_orig * $dest_width / $width_orig);
                            $delta_y = ($resize_height - $dest_height) / 2;
                        } else {
                            $resize_height = $dest_height;
                            $resize_width = (int) (($width_orig * $resize_height) / $height_orig);
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
                $resize_width = (int) (($width_orig * $resize_height) / $height_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $resize_width = $dest_width;
                    $resize_height = (int) ($height_orig * $dest_width / $width_orig);
                    $delta_y = ($resize_height - $dest_height) / 2;
                } elseif ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_width = $resize_width;
                }
            } else {
                // Image width > image height
                $resize_width = $dest_width;
                $resize_height = (int) ($height_orig * $dest_width / $width_orig);

                if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                    $resize_height = $dest_height;
                    $resize_width = (int) (($width_orig * $resize_height) / $height_orig);
                    $delta_x = ($resize_width - $dest_width) / 2;
                } elseif ($resize_mode != self::EXACT_RATIO_WITH_BORDERS) {
                    $dest_height = $resize_height;
                }
            }

            $image->resize(new Box($resize_width, $resize_height));

            $resizeFilter = 'imagick' === ConfigQuery::read('imagine_graphic_driver', 'gd')
                ? ImageInterface::FILTER_LANCZOS
                : ImageInterface::FILTER_UNDEFINED;

            $image->resize(new Box($resize_width, $resize_height), $resizeFilter);

            if ($resize_mode == self::EXACT_RATIO_WITH_BORDERS) {
                $border_width = (int) (($dest_width - $resize_width) / 2);
                $border_height = (int) (($dest_height - $resize_height) / 2);

                $canvas = new Box($dest_width, $dest_height);
                $layersCount = \count($image->layers());

                if ('imagick' === ConfigQuery::read('imagine_graphic_driver', 'gd') && $layersCount > 1) {
                    // If image has layers we apply transformation to all layers since paste method would flatten the image
                    $newImage = $imagine->create($canvas, $bg_color);
                    $resizedLayers = $newImage->layers();
                    $resizedLayers->remove(0);
                    for ($i = 0; $i < $layersCount; ++$i) {
                        $newImage2 = $imagine->create($canvas, $bg_color);
                        $resizedLayers[] = $newImage2->paste($image->layers()->get($i)->resize(new Box($resize_width, $resize_height), $resizeFilter), new Point($border_width, $border_height));
                    }

                    return $newImage;
                }

                return $imagine->create($canvas, $bg_color)
                        ->paste($image, new Point($border_width, $border_height));
            }

            if ($resize_mode == self::EXACT_RATIO_WITH_CROP) {
                $image->crop(
                    new Point($delta_x, $delta_y),
                    new Box($dest_width, $dest_height)
                );
            }
        }

        return $image;
    }

    /**
     * Create a new Imagine object using current driver configuration.
     *
     * @return ImagineInterface
     */
    protected function createImagineInstance(): \Imagine\Imagick\Imagine|\Imagine\Gmagick\Imagine|Imagine
    {
        $driver = ConfigQuery::read('imagine_graphic_driver', 'gd');

        return match ($driver) {
            'imagick' => new ImagickImagine(),
            'gmagick' => new GmagickImagine(),
            default => new Imagine(),
        };
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::IMAGE_PROCESS => ['processImage', 128],

            // Implemented in parent class BaseCachedFile
            TheliaEvents::IMAGE_CLEAR_CACHE => ['clearCache', 128],
            TheliaEvents::IMAGE_DELETE => ['deleteFile', 128],
            TheliaEvents::IMAGE_SAVE => ['saveFile', 128],
            TheliaEvents::IMAGE_UPDATE => ['updateFile', 128],
            TheliaEvents::IMAGE_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::IMAGE_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
        ];
    }
}

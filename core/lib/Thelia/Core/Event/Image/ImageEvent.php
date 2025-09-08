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

namespace Thelia\Core\Event\Image;

use Imagine\Image\ImageInterface;
use Thelia\Core\Event\CachedFileEvent;

class ImageEvent extends CachedFileEvent
{
    /** @var string The absolute path of the cached image file */
    protected ?string $cache_filepath = null;

    /** @var string The absolute URL of the cached version of the original image (in the web space) */
    protected ?string $original_file_url = null;

    /** @var string The absolute path of the cached version of the original image file */
    protected string $cache_original_filepath;

    /** @var string The image category (i.e. the subdirectory in image cache) */
    protected string $category;

    protected ?int $width = null;
    protected ?int $height = null;
    protected ?string $resize_mode = null;
    protected ?string $background_color = null;

    /** @var array a list of effects (grayscale, negative, mirror...), applied in the specified order. */
    protected array $effects = [];

    /** the rotation angle in degrees, none if zero or null */
    protected ?int $rotation = null;

    /** the quality of the result image, from 0 (!) to 100 */
    protected ?int $quality = null;

    protected ?ImageInterface $imageObject = null;
    protected bool $allowZoom;
    protected ?string $format = null;

    /**
     * @return bool true if the required image is the original image (resize_mode and background_color are not significant)
     */
    public function isOriginalImage(): bool
    {
        return (!isset($this->width) || 0 === $this->width) && (!isset($this->height) || 0 === $this->height) /* && empty($this->resize_mode) && empty($this->background_color) not significant */
            && [] === $this->effects && (!isset($this->rotation) || 0 === $this->rotation) && (!isset($this->quality) || 0 === $this->quality);
    }

    /**
     * @return string a hash identifiying the processing options
     */
    public function getOptionsHash(): string
    {
        if ($this->width === null || $this->height === null || $this->resize_mode === null || $this->background_color === null) {
            return '';
        }

        return md5(
            $this->width.$this->height.$this->resize_mode.$this->background_color.implode(',', $this->effects)
            .$this->rotation.$this->allowZoom,
        );
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getResizeMode(): string
    {
        return $this->resize_mode;
    }

    public function setResizeMode(string $resize_mode): static
    {
        $this->resize_mode = $resize_mode;

        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->background_color;
    }

    public function setBackgroundColor(string $background_color): static
    {
        $this->background_color = $background_color;

        return $this;
    }

    public function getEffects(): array
    {
        return $this->effects;
    }

    public function setEffects(array $effects): static
    {
        $this->effects = $effects;

        return $this;
    }

    public function getRotation(): int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): static
    {
        $this->rotation = $rotation;

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(int $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function getOriginalFileUrl(): ?string
    {
        return $this->original_file_url;
    }

    public function setOriginalFileUrl(string $original_file_url): static
    {
        $this->original_file_url = $original_file_url;

        return $this;
    }

    public function getCacheOriginalFilepath(): string
    {
        return $this->cache_original_filepath;
    }

    public function setCacheOriginalFilepath(string $cache_original_filepath): static
    {
        $this->cache_original_filepath = $cache_original_filepath;

        return $this;
    }

    public function setImageObject(?ImageInterface $imageObject): self
    {
        $this->imageObject = $imageObject;

        return $this;
    }

    public function getImageObject(): ?ImageInterface
    {
        return $this->imageObject;
    }

    public function getAllowZoom(): bool
    {
        return $this->allowZoom;
    }

    public function setAllowZoom(bool $allowZoom): self
    {
        $this->allowZoom = $allowZoom;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }
}

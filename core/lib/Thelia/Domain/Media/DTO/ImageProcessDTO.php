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

namespace Thelia\Domain\Media\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class ImageProcessDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $sourceFilepath,
        public string $cacheSubdirectory,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $resizeMode = null,
        public ?string $backgroundColor = null,
        public array $effects = [],
        public ?int $rotation = null,
        public ?int $quality = null,
        public bool $allowZoom = false,
        public ?string $format = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'source_filepath' => $this->sourceFilepath,
            'cache_subdirectory' => $this->cacheSubdirectory,
            'width' => $this->width,
            'height' => $this->height,
            'resize_mode' => $this->resizeMode,
            'background_color' => $this->backgroundColor,
            'effects' => $this->effects,
            'rotation' => $this->rotation,
            'quality' => $this->quality,
            'allow_zoom' => $this->allowZoom,
            'format' => $this->format,
        ];
    }
}

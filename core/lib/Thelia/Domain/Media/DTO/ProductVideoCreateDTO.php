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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Domain\Media\Video\VideoProvider;

/**
 * A video to attach to a product: either a platform and the identifier the address
 * resolved to, or a file to store in the video library.
 */
final readonly class ProductVideoCreateDTO
{
    public function __construct(
        public int $productId,
        public ?VideoProvider $provider = null,
        public ?string $externalId = null,
        public ?UploadedFile $uploadedFile = null,
        public ?int $thumbnailImageId = null,
        public string $locale = 'en_US',
        public ?string $title = null,
        public ?string $alt = null,
        public ?string $description = null,
        public ?string $chapo = null,
        public ?string $postscriptum = null,
        public bool $visible = true,
    ) {
    }
}

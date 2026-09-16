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
 * What a caller changes on a product video. A field left null is a field the
 * caller said nothing about, and it keeps the value it had.
 *
 * `thumbnailImageId` is the exception the caller has to be able to express: false
 * means "no thumbnail any more", which null could not say.
 */
final readonly class ProductVideoUpdateDTO
{
    public function __construct(
        public string $locale,
        public ?VideoProvider $provider = null,
        public ?string $externalId = null,
        public ?UploadedFile $uploadedFile = null,
        public int|false|null $thumbnailImageId = null,
        public ?string $title = null,
        public ?string $alt = null,
        public ?string $description = null,
        public ?string $chapo = null,
        public ?string $postscriptum = null,
        public ?bool $visible = null,
    ) {
    }
}

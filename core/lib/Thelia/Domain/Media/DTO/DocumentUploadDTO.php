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
use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class DocumentUploadDTO implements DTOEventActionInterface
{
    public function __construct(
        public int $parentId,
        public string $parentType,
        public UploadedFile $uploadedFile,
        public string $locale = 'en_US',
        public ?string $title = null,
        public ?string $chapo = null,
        public ?string $description = null,
        public ?string $postscriptum = null,
        public bool $visible = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'parent_id' => $this->parentId,
            'parent_type' => $this->parentType,
            'file' => $this->uploadedFile->getClientOriginalName(),
            'locale' => $this->locale,
            'title' => $this->title,
            'chapo' => $this->chapo,
            'description' => $this->description,
            'postscriptum' => $this->postscriptum,
            'visible' => $this->visible,
        ];
    }
}

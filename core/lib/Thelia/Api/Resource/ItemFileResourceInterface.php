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
namespace Thelia\Api\Resource;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ItemFileResourceInterface
{
    public static function getItemType(): string;

    /**
     * @return string Either "document" or "image"
     */
    public static function getFileType(): string;

    public function getItemId(): string;

    public function setFileToUpload(UploadedFile $fileToUpload): self;

    public function getFileToUpload(): UploadedFile;

    public function setFileUrl(?string $fileUrl): self;

    public function getFileUrl(): ?string;

    public function setFile(string $file): self;

    public function getFile(): string;
}

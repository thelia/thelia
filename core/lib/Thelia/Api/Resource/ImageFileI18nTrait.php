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

/**
 * The file an image stores for one language, read only.
 *
 * A file name means nothing without the file behind it: it is written by uploading one
 * (`POST /api/admin/<type>_images/{id}/file`), never through the translations. The
 * property is left uninitialized until a read fills it, so that a write never hands a
 * stale value back to the model.
 */
trait ImageFileI18nTrait
{
    public function __construct($data = [])
    {
        unset($data['file']);

        parent::__construct($data);
    }

    public function getFile(): ?string
    {
        return $this->file ?? null;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }
}

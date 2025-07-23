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

namespace Thelia\Tools;

class Image
{
    public static function isImage($filePath, $allowedImageTypes = null)
    {
        $imageFile = getimagesize($filePath);
        $imageType = $imageFile[2];

        if (!\is_array($allowedImageTypes) && \IMAGETYPE_UNKNOWN !== $imageType) {
            return true;
        }

        return \in_array($imageType, $allowedImageTypes, true);
    }
}

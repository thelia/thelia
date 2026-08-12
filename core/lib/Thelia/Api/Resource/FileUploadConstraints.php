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

use Thelia\Core\File\FileConfiguration;

/**
 * What the shop accepts on an upload, as the server enforces it.
 *
 * Published read only on every file resource, so that an integrator building an
 * upload form does not restate the lists and drift away from them. It follows the
 * two shop variables of FileConfiguration when they are set.
 *
 * The value is returned as an array rather than as an object on purpose: the
 * serialization groups of the file resources do not apply to a nested class, which
 * would be normalized as an empty object.
 */
final class FileUploadConstraints
{
    /**
     * Constraints applied to an upload of the given file type ("image", "document").
     *
     * - allowedMimeTypes: accepted mime types; empty means any mime type is accepted
     * - allowedExtensions: extensions those mime types may carry, ready for an
     *   "accept" attribute; empty means the extension is not what is checked
     * - forbiddenExtensions: extensions always refused, whatever the configuration says
     *
     * @return array{allowedMimeTypes: list<string>, allowedExtensions: list<string>, forbiddenExtensions: list<string>}
     */
    public static function forFileType(string $fileType): array
    {
        $policy = FileConfiguration::getConfig($fileType);

        $extensions = [];

        foreach ($policy['validMimeTypes'] as $mimeTypeExtensions) {
            foreach ($mimeTypeExtensions as $extension) {
                $extensions[] = $extension;
            }
        }

        return [
            'allowedMimeTypes' => array_keys($policy['validMimeTypes']),
            'allowedExtensions' => array_values(array_unique($extensions)),
            'forbiddenExtensions' => array_values(array_unique(
                [...$policy['extBlackList'], ...FileConfiguration::SERVER_EXECUTABLE_EXTENSIONS],
            )),
        ];
    }
}

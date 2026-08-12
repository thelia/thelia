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

namespace Thelia\Core\File;

use Thelia\Model\ConfigQuery;

/**
 * Single source of truth for what may be uploaded in a Thelia shop.
 *
 * Images are restricted by a mime type whitelist, documents by an extension
 * blacklist. Both lists ship with a safe default and can be adjusted per shop
 * through a configuration variable, so that a legitimate file type nobody
 * anticipated does not require a core patch:
 *
 *   image_upload_allowed_mime_types    image/jpeg, image/png, image/avif
 *   document_upload_forbidden_extensions   php, phtml, exe
 *
 * An empty or missing variable means "use the default list". Loosening these
 * variables can never re-enable a server-executable extension: that floor is
 * enforced unconditionally by FileProcessorService.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class FileConfiguration
{
    public const IMAGE_MIME_TYPES_VARIABLE = 'image_upload_allowed_mime_types';

    public const DOCUMENT_EXTENSION_BLACKLIST_VARIABLE = 'document_upload_forbidden_extensions';

    /**
     * Mime types accepted for an image upload, in the absence of a shop configuration.
     */
    public const DEFAULT_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * Extensions refused for a document upload, in the absence of a shop configuration.
     */
    public const DEFAULT_DOCUMENT_EXTENSION_BLACKLIST = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phtml', 'phtm', 'pht', 'phps', 'phpt', 'phar',
        'shtml', 'shtm', 'stm',
        'asp', 'aspx', 'jsp', 'jspx',
        'cgi', 'pl', 'py', 'sh',
        'htaccess', 'htpasswd',
        'exe', 'bat', 'cmd', 'com',
    ];

    /**
     * Extensions a web server may be configured to execute. They are refused on
     * every upload path, whatever the configuration above says, and whether they
     * end the file name or sit in the middle of it ("shell.php.jpg"). Only
     * extensions that never collide with a legitimate locale or type suffix are
     * listed, to avoid false positives.
     */
    public const SERVER_EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phtml', 'phtm', 'pht', 'phps', 'phpt', 'phar',
        'shtml', 'shtm', 'stm',
        'htaccess', 'htpasswd',
    ];

    /**
     * Extensions a mime type is allowed to carry, so that a PNG named ".jpg"
     * is rejected. A mime type absent from this table is matched on the mime
     * type alone, which lets a shop add a new one without a core change.
     */
    private const MIME_TYPE_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'image/avif' => ['avif'],
        'image/bmp' => ['bmp'],
        'image/tiff' => ['tif', 'tiff'],
        'image/vnd.microsoft.icon' => ['ico'],
        'image/x-icon' => ['ico'],
        'image/heic' => ['heic'],
    ];

    /**
     * @return array{objectType: string, validMimeTypes: array<string, list<string>>, extBlackList: list<string>}
     */
    public static function getImageConfig(): array
    {
        return [
            'objectType' => 'image',
            'validMimeTypes' => self::buildMimeTypeMap(
                self::readList(self::IMAGE_MIME_TYPES_VARIABLE, self::DEFAULT_IMAGE_MIME_TYPES),
            ),
            'extBlackList' => [],
        ];
    }

    /**
     * @return array{objectType: string, validMimeTypes: array<string, list<string>>, extBlackList: list<string>}
     */
    public static function getDocumentConfig(): array
    {
        return [
            'objectType' => 'document',
            'validMimeTypes' => [],
            'extBlackList' => self::readList(
                self::DOCUMENT_EXTENSION_BLACKLIST_VARIABLE,
                self::DEFAULT_DOCUMENT_EXTENSION_BLACKLIST,
            ),
        ];
    }

    /**
     * Constraints to apply to an upload of the given object type, for callers
     * that do not carry their own policy. Unknown object types get no constraint.
     *
     * @return array{objectType: string, validMimeTypes: array<string, list<string>>, extBlackList: list<string>}
     */
    public static function getConfig(string $objectType): array
    {
        return match ($objectType) {
            'image' => self::getImageConfig(),
            'document' => self::getDocumentConfig(),
            default => ['objectType' => $objectType, 'validMimeTypes' => [], 'extBlackList' => []],
        };
    }

    /**
     * Returns the first server-executable extension segment found in the file name
     * (terminal or not), or null when the name is safe.
     */
    public static function findExecutableExtension(string $fileName): ?string
    {
        $segments = explode('.', strtolower($fileName));
        // Drop the base name; only extension segments are relevant.
        array_shift($segments);

        foreach ($segments as $segment) {
            if (\in_array($segment, self::SERVER_EXECUTABLE_EXTENSIONS, true)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * @param list<string> $default
     *
     * @return list<string>
     */
    private static function readList(string $variableName, array $default): array
    {
        $raw = ConfigQuery::read($variableName);

        if (!\is_string($raw) || '' === trim($raw)) {
            return $default;
        }

        $values = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $raw),
        ), static fn (string $value): bool => '' !== $value));

        return [] === $values ? $default : $values;
    }

    /**
     * @param list<string> $mimeTypes
     *
     * @return array<string, list<string>>
     */
    private static function buildMimeTypeMap(array $mimeTypes): array
    {
        $map = [];

        foreach ($mimeTypes as $mimeType) {
            $map[$mimeType] = self::MIME_TYPE_EXTENSIONS[$mimeType] ?? [];
        }

        return $map;
    }
}

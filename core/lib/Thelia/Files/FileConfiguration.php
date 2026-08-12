<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Files;

/**
 * Class FileConfiguration.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class FileConfiguration
{
    /**
     * Extensions a web server may be configured to execute. They are refused whatever
     * the upload path allows otherwise, and whether they end the file name or sit in
     * the middle of it ("shell.php.jpg"). Only extensions that never collide with a
     * legitimate locale or type suffix are listed, to avoid false positives.
     */
    public const SERVER_EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phtml', 'phtm', 'pht', 'phps', 'phpt', 'phar',
        'shtml', 'shtm', 'stm',
        'htaccess', 'htpasswd',
    ];

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

    public static function getImageConfig()
    {
        return [
            'objectType' => 'image',
            'validMimeTypes' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/gif' => ['gif'],
                'image/webp' => ['webp'],
                'image/svg+xml' => ['svg'],
            ],
            'extBlackList' => [],
        ];
    }

    public static function getDocumentConfig()
    {
        return [
            'objectType' => 'document',
            'validMimeTypes' => [],
            'extBlackList' => [
                'php',
                'php3',
                'php4',
                'php5',
                'php6',
                'asp',
                'aspx',
            ],
        ];
    }
}

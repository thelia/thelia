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
 * Class FileConfiguration
 * @package Thelia\Files
 * @author manuel raynaud <manu@raynaud.io>
 */
class FileConfiguration
{
    public static function getImageConfig()
    {
        return [
            'objectType' => 'image',
            'validMimeTypes' => [
                'image/jpeg' => ["jpg", "jpeg"],
                'image/png' => ["png"],
                'image/gif' => ["gif"],
            ],
            'extBlackList' => []
        ];
    }

    public static function getDocumentConfig()
    {
        return [
            'objectType' => 'document',
            'validMimeTypes' => [],
            'extBlackList' => [
                "php",
                "php3",
                "php4",
                "php5",
                "php6",
                "asp",
                "aspx",
            ]
        ];
    }
}

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

/**
 * Class Password.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Password
{
    private static function randgen(string $letter, $length): string
    {
        $string = '';
        do {
            $string .= substr(str_shuffle($letter), 0, 1);
        } while (\strlen($string) < $length);

        return $string;
    }

    /**
     * generate a Random password with defined length.
     *
     * @param int $length
     */
    public static function generateRandom($length = 8): string
    {
        $letter = 'abcdefghijklmnopqrstuvwxyz';
        $letter .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $letter .= '0123456789';

        return self::randgen($letter, $length);
    }

    public static function generateHexaRandom($length = 8): string
    {
        $letter = 'ABCDEF';
        $letter .= '0123456789';

        return self::randgen($letter, $length);
    }
}

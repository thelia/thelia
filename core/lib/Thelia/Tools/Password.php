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
     */
    public static function generateRandom(int $length = 8): string
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

    /**
     * bcrypt hash at PHP's default cost, unless THELIA_PASSWORD_HASH_COST sets one.
     *
     * The default cost went from 10 to 12 in PHP 8.4, four times the work per hash.
     * A test suite that creates and logs in accounts by the hundred lowers it.
     */
    public static function hash(string $password): string
    {
        $cost = $_SERVER['THELIA_PASSWORD_HASH_COST'] ?? $_ENV['THELIA_PASSWORD_HASH_COST'] ?? '';

        if ('' === $cost) {
            return password_hash($password, \PASSWORD_BCRYPT);
        }

        $cost = filter_var($cost, \FILTER_VALIDATE_INT);

        if (false === $cost) {
            throw new \InvalidArgumentException('THELIA_PASSWORD_HASH_COST must be an integer.');
        }

        return password_hash($password, \PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

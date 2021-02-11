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

if (version_compare(PHP_VERSION, '5.5', '<')) {
    die("Your server is running PHP ".PHP_VERSION.". Thelia 2 requires PHP 5.5 or better.");
}

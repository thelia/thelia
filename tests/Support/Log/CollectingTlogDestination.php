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

namespace Thelia\Tests\Support\Log;

use Thelia\Log\AbstractTlogDestination;

/**
 * A Tlog destination that keeps every line it is given, for a test that asserts what
 * the code under test logs, and at which level the log keeps it.
 */
final class CollectingTlogDestination extends AbstractTlogDestination
{
    /** @var list<string> */
    public static array $lines = [];

    public function getTitle(): string
    {
        return 'Collector';
    }

    public function getDescription(): string
    {
        return 'Keeps the lines for a test';
    }

    public function add($string): void
    {
        self::$lines[] = (string) $string;
    }

    public function write(&$res): void
    {
    }
}

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

namespace Thelia\Tests\Http\Flexy;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the `http-flexy` PHPUnit suite from reporting
 * "No tests executed!" (which turns into a non-zero exit code and
 * breaks `composer test`) until the real Flexy HTTP tests land
 * under this directory. Delete this file as soon as the first real
 * Flexy test is added.
 */
final class SuitePlaceholderTest extends TestCase
{
    public function testHttpFlexySuiteExists(): void
    {
        self::assertTrue(true);
    }
}

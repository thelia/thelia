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

namespace Thelia\Tests\Http\BackOffice;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the `http-backoffice` PHPUnit suite from reporting
 * "No tests executed!" until the real Smarty back-office HTTP tests
 * land under this directory. Delete this file as soon as the first
 * real back-office test is added.
 */
final class SuitePlaceholderTest extends TestCase
{
    public function testHttpBackOfficeSuiteExists(): void
    {
        self::assertTrue(true);
    }
}

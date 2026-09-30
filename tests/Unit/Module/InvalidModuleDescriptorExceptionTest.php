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

namespace Thelia\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;
use Thelia\Module\Exception\InvalidModuleDescriptorException;

/**
 * The message quotes a module.xml and a module directory name, both written by whoever
 * shipped the module: the install entry points print it as is.
 */
final class InvalidModuleDescriptorExceptionTest extends TestCase
{
    public function testTheMessageCarriesNoControlCharacterButTabsAndLineFeeds(): void
    {
        $exception = new InvalidModuleDescriptorException("<enabled-by-default> in \e[31mAcme\e[0m/Config/module.xml\r\n\tmust be 0 or 1");

        self::assertSame("<enabled-by-default> in ?[31mAcme?[0m/Config/module.xml?\n\tmust be 0 or 1", $exception->getMessage());
    }
}

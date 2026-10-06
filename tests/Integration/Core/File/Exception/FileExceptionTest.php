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

namespace Thelia\Tests\Integration\Core\File\Exception;

use Thelia\Core\File\Exception\FileException;
use Thelia\Test\IntegrationTestCase;

final class FileExceptionTest extends IntegrationTestCase
{
    public function testAnExceptionBuiltFromAMessageAloneCarriesNoCode(): void
    {
        $exception = new FileException('The file cannot be stored.');

        self::assertSame('The file cannot be stored.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }
}

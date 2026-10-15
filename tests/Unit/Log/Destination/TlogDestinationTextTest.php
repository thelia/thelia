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

namespace Thelia\Tests\Unit\Log\Destination;

use PHPUnit\Framework\TestCase;
use Thelia\Log\Destination\TlogDestinationText;

final class TlogDestinationTextTest extends TestCase
{
    public function testALineIsPrintedTrimmedOnItsOwnLine(): void
    {
        $this->expectOutputString("Stock is running low\n");

        (new TlogDestinationText())->add("  Stock is running low \n");
    }
}

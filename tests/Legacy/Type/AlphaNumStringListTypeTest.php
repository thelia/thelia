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

namespace Thelia\Tests\Type;

use PHPUnit\Framework\TestCase;
use Thelia\Type\AlphaNumStringListType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AlphaNumStringListTypeTest extends TestCase
{
    public function testAlphaNumStringListType(): void
    {
        $type = new AlphaNumStringListType();
        $this->assertTrue($type->isValid('FOO1,FOO_2,FOO-3'));
        $this->assertFalse($type->isValid('FOO.1,FOO$_2,FOO-3'));
    }

    public function testFormatAlphaNumStringListType(): void
    {
        $type = new AlphaNumStringListType();
        $this->assertIsArray($type->getFormattedValue('FOO1,FOO_2,FOO-3'));
        $this->assertNull($type->getFormattedValue('5€'));

        $result = $type->getFormattedValue('FOO');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
}

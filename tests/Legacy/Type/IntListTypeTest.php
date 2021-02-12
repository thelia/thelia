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
use Thelia\Type\IntListType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class IntListTypeTest extends TestCase
{
    public function testIntListType(): void
    {
        $intListType = new IntListType();
        $this->assertTrue($intListType->isValid('1'));
        $this->assertTrue($intListType->isValid('1,2,3'));
        $this->assertFalse($intListType->isValid('1,2,3.3'));
    }

    public function testFormatIntListType(): void
    {
        $intListType = new IntListType();
        $this->assertIsArray($intListType->getFormattedValue('1,2,3'));
        $this->assertNull($intListType->getFormattedValue('foo'));
    }
}

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
use Thelia\Type\IntType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntTypeTest extends TestCase
{
    public function testIntType()
    {
        $intType = new IntType();
        $this->assertTrue($intType->isValid('1'));
        $this->assertTrue($intType->isValid(2));
        $this->assertFalse($intType->isValid('3.3'));
    }
}

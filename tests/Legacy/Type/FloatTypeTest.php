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
use Thelia\Type\FloatType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FloatTypeTest extends TestCase
{
    public function testFloatType(): void
    {
        $floatType = new FloatType();
        $this->assertTrue($floatType->isValid('1.1'));
        $this->assertTrue($floatType->isValid(2.2));
        $this->assertFalse($floatType->isValid('foo'));
    }
}

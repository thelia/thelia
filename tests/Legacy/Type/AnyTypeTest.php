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
use Thelia\Type\AnyType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class AnyTypeTest extends TestCase
{
    public function testAnyType()
    {
        $anyType = new AnyType();
        $this->assertTrue($anyType->isValid(md5(rand(1000, 10000))));
    }
}

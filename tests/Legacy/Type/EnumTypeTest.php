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
use Thelia\Type\EnumType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class EnumTypeTest extends TestCase
{
    public function testEnumType(): void
    {
        $enumType = new EnumType(['cat', 'dog']);
        $this->assertTrue($enumType->isValid('cat'));
        $this->assertTrue($enumType->isValid('dog'));
        $this->assertFalse($enumType->isValid('monkey'));
        $this->assertFalse($enumType->isValid('catdog'));
    }
}

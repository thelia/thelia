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
use Thelia\Type\EnumListType;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class EnumListTypeTest extends TestCase
{
    public function testEnumListType()
    {
        $enumListType = new EnumListType(['cat', 'dog', 'frog']);
        $this->assertTrue($enumListType->isValid('cat'));
        $this->assertTrue($enumListType->isValid('cat,dog'));
        $this->assertFalse($enumListType->isValid('potato'));
        $this->assertFalse($enumListType->isValid('cat,monkey'));
    }

    public function testFormatEnumListType()
    {
        $enumListType = new EnumListType(['cat', 'dog', 'frog']);
        $this->assertIsArray($enumListType->getFormattedValue('cat,dog'));
        $this->assertNull($enumListType->getFormattedValue('cat,monkey'));
    }
}

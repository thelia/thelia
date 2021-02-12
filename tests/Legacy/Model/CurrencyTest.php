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

namespace Thelia\Tests\Model;

use PHPUnit\Framework\TestCase;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Class CurrencyTest.
 *
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class CurrencyTest extends TestCase
{
    public function testGetDefaultCurrency(): void
    {
        $expectedCurrency = CurrencyQuery::create()->findOneByByDefault(true);
        $actualCurrency = Currency::getDefaultCurrency();

        $this->assertEquals($expectedCurrency->getId(), $actualCurrency->getId());
    }
}

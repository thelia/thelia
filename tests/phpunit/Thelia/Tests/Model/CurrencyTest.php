<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Model;

use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Class CurrencyTest
 * @package Thelia\Tests\Model
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class CurrencyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetDefaultCurrency()
    {
        $expectedCurrency = CurrencyQuery::create()->findOneByByDefault(true);
        $actualCurrency = Currency::getDefaultCurrency();

        $this->assertEquals($expectedCurrency->getId(), $actualCurrency->getId());
    }
}

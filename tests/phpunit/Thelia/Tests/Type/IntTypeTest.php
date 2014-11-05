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

namespace Thelia\Tests\Type;

use Thelia\Type\IntType;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class IntTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testIntType()
    {
        $intType = new IntType();
        $this->assertTrue($intType->isValid('1'));
        $this->assertTrue($intType->isValid(2));
        $this->assertFalse($intType->isValid('3.3'));
    }
}

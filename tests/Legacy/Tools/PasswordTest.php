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

namespace Thelia\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Thelia\Tools\Password;

/**
 * Class PasswordTest
 * @package Thelia\Tests\Type
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class PasswordTest extends TestCase
{
    public function testGenerateRandom()
    {
        $length = 8;
        $password = Password::generateRandom($length);

        $this->assertEquals($length, \strlen($password));
    }

    public function testGenerateHexaRandom()
    {
        $length = 8;
        $password = Password::generateHexaRandom($length);

        $this->assertEquals($length, \strlen($password));
    }
}

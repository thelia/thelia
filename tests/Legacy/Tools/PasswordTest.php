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

namespace Thelia\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Thelia\Tools\Password;

/**
 * Class PasswordTest.
 *
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

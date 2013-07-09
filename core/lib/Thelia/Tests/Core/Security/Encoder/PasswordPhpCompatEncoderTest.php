<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 09/07/13
 * Time: 10:02
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Tests\Security\Encoder;


use Thelia\Core\Security\Encoder\PasswordPhpCompatEncoder;

class PasswordPhpCompatEncoderTest extends \PHPUnit_Framework_TestCase {

    protected $encoder;

    public function setUp()
    {
        $this->encoder = new PasswordPhpCompatEncoder();
    }

    public function testEncode()
    {
        $hash = $this->encoder->encode("foo", PASSWORD_BCRYPT);

        $this->assertEquals($hash, crypt("foo", $hash));
    }

}
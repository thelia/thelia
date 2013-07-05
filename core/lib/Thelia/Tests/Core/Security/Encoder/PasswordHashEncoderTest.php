<?php

use Thelia\Core\Security\Encoder\PasswordHashEncoder;

class PasswordHashEncoderTest extends \PHPUnit_Framework_TestCase
{
    public function testEncode()
    {
        $encoder = new PasswordHashEncoder();

        $pass = $encoder->encode('password', 'sha512', 'a simple salt');

       // echo "PASS=\{$pass\}";

        $this->assertEquals("L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==", $pass, "Expected password not found.");
    }

    public function testIsEqual()
    {
    	$encoder = new PasswordHashEncoder();

    	$exp = "L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==";

    	$this->assertTrue($encoder->isEqual($exp, 'password', 'sha512', 'a simple salt'));
     }

     public function testWrongPass()
     {
     	$encoder = new PasswordHashEncoder();

     	$exp = "L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==";

     	$this->assertFalse($encoder->isEqual($exp, 'grongron', 'sha512', 'a simple salt'));
     }

     public function testWrongSalt()
     {
     	$encoder = new PasswordHashEncoder();

     	$exp = "L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==";

     	$this->assertFalse($encoder->isEqual($exp, 'password', 'sha512', 'another salt'));
     }

     public function testWrongAlgo()
     {
     	$encoder = new PasswordHashEncoder();

     	$exp = "L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==";

     	$this->assertFalse($encoder->isEqual($exp, 'password', 'md5', 'another salt'));
     }

    /**
    * @expectedException LogicException
    */
     public function testUnsupportedAlgo()
     {
     	$encoder = new PasswordHashEncoder();

     	$exp = "L3f/gGy4nBVhi8WSsC1a7E9JM8U+rtk6ZT+NiqX8M1UDJv6mahQEZ1z2cN/y9pixH+hgWbkBitONMiXWscomoQ==";

     	$encoder->isEqual($exp, 'password', 'sbonk', 'another salt');
     }

   /**
    * @expectedException LogicException
    */
     public function testEncodeWrongAlgorithm()
    {
    	$encoder = new PasswordHashEncoder();

    	$encoder->encode('password', 'pouët', 'a simple salt');
    }
}
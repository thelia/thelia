<?php
use Thelia\Core\Security\Token\UsernamePasswordToken;

class UsernamePasswordTokenTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $token = new UsernamePasswordToken('username', 'password');

        $this->assertFalse($token->isAuthenticated());

        $token = new UsernamePasswordToken('username', 'password', true);
        $this->assertTrue($token->isAuthenticated());
    }

    /**
    * @expectedException LogicException
    */
    public function testSetAuthenticatedToTrue()
    {
        $token = new UsernamePasswordToken('foo', 'bar', true);
        $token->setAuthenticated(true);
    }

    public function testSetAuthenticatedToFalse()
    {
        $token = new UsernamePasswordToken('foo', 'bar', true);
        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    public function testEraseCredentials()
    {
        $token = new UsernamePasswordToken('foo', 'bar', true);
        $token->eraseCredentials();
        $this->assertEquals('', $token->getCredentials());
    }
}
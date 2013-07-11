<?php

namespace Thelia\Core\Security\Token;

use Thelia\Core\Security\User\UserInterface;

/**
* UsernamePasswordToken implements a username and password token.
*
* @author Fabien Potencier <fabien@symfony.com>
*/
class UsernamePasswordToken extends AbstractToken
{
    private $credentials;

    /**
    * Constructor.
    *
    * @param string $user The username (like a nickname, email address, etc.), or a UserInterface instance or an object implementing a __toString method.
    * @param string $password The password of the user
     *
    * @throws \InvalidArgumentException
    */
    public function __construct($username, $password, array $roles = array())
    {
        $this->setUser($username);
        $this->credentials = $password;

        parent::setAuthenticated(count($roles) > 0);
    }

    /**
    * {@inheritdoc}
    */
    public function setAuthenticated($isAuthenticated)
    {
        if ($isAuthenticated) {
            throw new \LogicException('Cannot set this token to trusted after instantiation.');
        }

        parent::setAuthenticated(false);
    }

    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
    * {@inheritdoc}
    */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->credentials = null;
    }

    /**
    * {@inheritdoc}
    */
    public function serialize()
    {
        return serialize(array($this->credentials, $this->providerKey, parent::serialize()));
    }

    /**
    * {@inheritdoc}
    */
    public function unserialize($serialized)
    {
        list($this->credentials, $this->providerKey, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
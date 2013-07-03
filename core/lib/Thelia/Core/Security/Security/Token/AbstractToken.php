<?php
namespace Thelia\Core\Security\Token;

use Thelia\Core\Security\User\UserInterface;

/**
* Base class for Token instances.
*
* @author Fabien Potencier <fabien@symfony.com>
* @author Johannes M. Schmitt <schmittjoh@gmail.com>
*/
abstract class AbstractToken implements TokenInterface
{
    private $user;
    private $authenticated;

    /**
    * Constructor.
    *
    * @param RoleInterface[] $roles An array of roles
    *
    * @throws \InvalidArgumentException
    */
    public function __construct()
    {
        $this->authenticated = false;
    }

    /**
    * {@inheritdoc}
    */
    public function getUsername()
    {
        if ($this->user instanceof UserInterface) {
            return $this->user->getUsername();
        }

        return (string) $this->user;
    }

    public function getUser()
    {
        return $this->user;
    }

    /**
    * Sets the user in the token.
    *
    * The user can be a UserInterface instance, or an object implementing
    * a __toString method or the username as a regular string.
    *
    * @param mixed $user The user
    * @throws \InvalidArgumentException
    */
    public function setUser($user)
    {
        if (!($user instanceof UserInterface || is_string($user))) {
            throw new \InvalidArgumentException('$user must be an instanceof UserInterface, or a primitive string.');
        }

        if (null === $this->user) {
            $changed = false;
        } elseif ($this->user instanceof UserInterface) {
            if (!$user instanceof UserInterface) {
                $changed = true;
            } else {
                $changed = $this->hasUserChanged($user);
            }
        } elseif ($user instanceof UserInterface) {
            $changed = true;
        } else {
            $changed = (string) $this->user !== (string) $user;
        }

        if ($changed) {
            $this->setAuthenticated(false);
        }

        $this->user = $user;
    }

    /**
    * {@inheritdoc}
    */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
    * {@inheritdoc}
    */
    public function setAuthenticated($authenticated)
    {
        $this->authenticated = (Boolean) $authenticated;
    }

    /**
    * {@inheritdoc}
    */
    public function eraseCredentials()
    {
        if ($this->getUser() instanceof UserInterface) {
            $this->getUser()->eraseCredentials();
        }
    }

    /**
    * {@inheritdoc}
    */
    public function serialize()
    {
        return serialize(array($this->user, $this->authenticated));
    }

    /**
    * {@inheritdoc}
    */
    public function unserialize($serialized)
    {
        list($this->user, $this->authenticated) = unserialize($serialized);
    }

    private function hasUserChanged(UserInterface $user)
    {
        if (!($this->user instanceof UserInterface)) {
            throw new \BadMethodCallException('Method "hasUserChanged" should be called when current user class is instance of "UserInterface".');
        }

        if ($this->user instanceof EquatableInterface) {
            return ! (Boolean) $this->user->isEqualTo($user);
        }

        if ($this->user->getPassword() !== $user->getPassword()) {
            return true;
        }

        if ($this->user->getSalt() !== $user->getSalt()) {
            return true;
        }

        if ($this->user->getUsername() !== $user->getUsername()) {
            return true;
        }

        return false;
    }
}
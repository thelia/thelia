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

namespace Thelia\Core\Security\UserProvider;

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\PayloadAwareUserProviderInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;

class AdminUserProvider implements PayloadAwareUserProviderInterface
{
    private $cache = [];

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try with login name
        $admin = AdminQuery::create()
            ->filterByLogin($identifier, Criteria::EQUAL)
            ->findOne();

        // Try with email address
        if (null == $admin && !empty($identifier)) {
            $admin = AdminQuery::create()
                ->filterByEmail($identifier, Criteria::EQUAL)
                ->findOne();
        }

        if (null === $admin) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return $admin;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof Admin) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', \get_class($user)));
        }

        $user = AdminQuery::create()
            ->filterByLogin($user->getUserIdentifier(), Criteria::EQUAL)
            ->findOne();

        if (null === $user) {
            throw new UserNotFoundException('User not exist');
        }

        return $user;
    }

    public function supportsClass(string $class)
    {
        return Admin::class === $class || is_subclass_of($class, Admin::class);
    }

    public function loadUserByUsernameAndPayload(string $username, array $payload): UserInterface
    {
        return $this->loadUserByIdentifierAndPayload($username, $payload);
    }

    public function loadUserByIdentifierAndPayload(string $userIdentifier, array $payload): UserInterface
    {
        if (!isset($payload['type']) || $payload['type'] !== Admin::class) {
            throw new UnsupportedUserException(sprintf('User "%s" is not supported on this route.', $userIdentifier));
        }

        if (isset($this->cache[$userIdentifier])) {
            return $this->cache[$userIdentifier];
        }

        return $this->cache[$userIdentifier] = $this->loadUserByIdentifier($userIdentifier);
    }
}

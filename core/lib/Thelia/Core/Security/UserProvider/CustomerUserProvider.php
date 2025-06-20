<?php

declare(strict_types=1);

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
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

class CustomerUserProvider implements PayloadAwareUserProviderInterface
{
    private array $cache = [];

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $customer = CustomerQuery::create()
            ->filterByEmail($identifier, Criteria::EQUAL)
            ->findOne();

        if (null === $customer) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return $customer;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        $user = CustomerQuery::create()
            ->filterByLogin($user->getUserIdentifier(), Criteria::EQUAL)
            ->findOne();

        if (null === $user) {
            throw new UserNotFoundException('User not exist');
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return Customer::class === $class || is_subclass_of($class, Customer::class);
    }

    public function loadUserByUsernameAndPayload(string $username, array $payload): UserInterface
    {
        return $this->loadUserByIdentifierAndPayload($username, $payload);
    }

    public function loadUserByIdentifierAndPayload(string $userIdentifier, array $payload): UserInterface
    {
        if (!isset($payload['type']) || $payload['type'] !== Customer::class) {
            throw new UnsupportedUserException(sprintf('User "%s" is not supported on this route.', $userIdentifier));
        }

        return $this->cache[$userIdentifier] ?? $this->cache[$userIdentifier] = $this->loadUserByIdentifier($userIdentifier);
    }
}

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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

class CustomerUserProvider implements UserProviderInterface
{
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

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $user =  CustomerQuery::create()
            ->filterByLogin($user->getUserIdentifier(), Criteria::EQUAL)
            ->findOne();

        if (null === $user) {
            throw new UserNotFoundException("User not exist");
        }

        return $user;
    }

    public function supportsClass(string $class)
    {
        return Customer::class === $class || is_subclass_of($class, Customer::class);
    }
}

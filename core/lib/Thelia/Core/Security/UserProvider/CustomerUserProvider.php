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
use Thelia\Core\Security\GuestToken;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Loads the account behind an address, for everything that signs someone in.
 *
 * Guest rows are not accounts, and loadUserByIdentifier() never returns one. A guest row
 * is written by the checkout for whoever types an address: nobody proved they own it, it
 * holds no password, and it carries the orders of every earlier visitor who used that
 * address. A guest that chose a password keeps `is_guest = 1` until the activation code
 * mailed to it is answered, so it is refused here too — that is what makes the code
 * mandatory rather than decorative. An address may carry both a guest row and a real
 * account; the account is the one this returns.
 *
 * The one exception is the guest checkout token, and it is not a sign-in: it is a short
 * lived credential the shop minted itself for one cart, it grants ROLE_GUEST and nothing
 * more, and it is asked for by name below. A guest row has no way of producing any other
 * kind of token, since it has nothing to authenticate with.
 */
class CustomerUserProvider implements PayloadAwareUserProviderInterface
{
    private array $cache = [];

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->load($identifier, guestRowsAllowed: false);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(\sprintf('Invalid user class "%s".', $user::class));
        }

        return $this->load($user->getUserIdentifier(), guestRowsAllowed: false);
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
        if (!isset($payload['type']) || Customer::class !== $payload['type']) {
            throw new UnsupportedUserException(\sprintf('User "%s" is not supported on this route.', $userIdentifier));
        }

        // Only a token that asks for the guest role reaches a guest row, and JwtListener
        // pins such a token to ROLE_GUEST whatever else it claims. A token that asks for
        // anything else lands on the account, or on nothing.
        $guestRowsAllowed = \in_array(
            GuestToken::ROLE,
            \is_array($payload['roles'] ?? null) ? $payload['roles'] : [],
            true,
        );

        $cacheKey = ($guestRowsAllowed ? 'guest:' : 'account:').$userIdentifier;

        return $this->cache[$cacheKey] ??= $this->load($userIdentifier, $guestRowsAllowed);
    }

    private function load(string $identifier, bool $guestRowsAllowed): Customer
    {
        // A guest token was minted for the guest row, so that is the row it names. The
        // fallback covers the token outliving its own conversion: once the code is
        // answered the row is an account and there is no guest row left, and the token
        // stays a guest token — JwtListener pins it to ROLE_GUEST either way.
        $customer = $guestRowsAllowed
            ? $this->findRow($identifier, guest: true) ?? $this->findRow($identifier, guest: false)
            : $this->findRow($identifier, guest: false);

        if (null === $customer) {
            $e = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return $customer;
    }

    /**
     * Newest first: an address may carry more than one row of either kind, and which one
     * comes back must not be left to the database.
     */
    private function findRow(string $identifier, bool $guest): ?Customer
    {
        return CustomerQuery::create()
            ->filterByEmail($identifier, Criteria::EQUAL)
            ->filterByIsGuest($guest ? 1 : 0)
            ->orderById(Criteria::DESC)
            ->findOne();
    }
}

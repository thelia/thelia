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

namespace Thelia\Core\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\Customer;

/**
 * A simple security manager, in charge of checking user.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SecurityContext
{
    /** @var \WeakReference<Admin>|null the session admin already checked against the database */
    private ?\WeakReference $revalidatedAdminUser = null;

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    private function getSession(): ?Session
    {
        $request = $this->requestStack->getMainRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }

    /**
     * Gets the currently authenticated user in  the admin, or null if none is defined.
     *
     * @return UserInterface|null A UserInterface instance or null if no user is available
     */
    public function getAdminUser(): mixed
    {
        $user = $this->getSession()?->getAdminUser();

        if ($user instanceof Admin && $this->revalidatedAdminUser?->get() !== $user) {
            return $this->revalidateAdminUser($user);
        }

        return $user;
    }

    /**
     * Reload the session admin from the database, once per request, so that a
     * deleted admin (or one whose profile was changed) does not keep a
     * privileged session built from stale serialized data.
     */
    private function revalidateAdminUser(Admin $sessionAdminUser): ?Admin
    {
        $freshAdminUser = AdminQuery::create()->findPk($sessionAdminUser->getId());

        if (null === $freshAdminUser) {
            $this->clearAdminUser();

            return null;
        }

        $freshAdminUser->eraseCredentials();

        $this->getSession()?->setAdminUser($freshAdminUser);
        $this->revalidatedAdminUser = \WeakReference::create($freshAdminUser);

        return $freshAdminUser;
    }

    /**
     * Check if an admin user is logged in.
     *
     * @return bool true if an admin user is logged in, false otherwise
     */
    public function hasAdminUser(): bool
    {
        return null !== $this->getAdminUser();
    }

    /**
     * Gets the currently authenticated customer, or null if none is defined.
     *
     * @return Customer|null A UserInterface instance or null if no user is available
     */
    public function getCustomerUser(): mixed
    {
        return $this->getSession()?->getCustomerUser();
    }

    /**
     * Check if a customer user is logged in.
     *
     * @return bool true if a customer is logged in, false otherwise
     */
    public function hasCustomerUser(): bool
    {
        return null !== $this->getSession()?->getCustomerUser();
    }

    /**
     * Check whether the session customer is a guest who is checking out rather than
     * someone who signed in.
     *
     * A guest sits in the session under the same key as a signed-in customer, because
     * the checkout needs a customer to build the order from. What tells them apart is
     * the row itself — `customer.is_guest`, which stays 1 until an activation code is
     * answered — and never a session flag: a flag is one write away from saying the
     * opposite of the row, and the whole of what closes the account pages to a guest
     * would go with it.
     */
    public function hasGuestCustomerUser(): bool
    {
        $customer = $this->getCustomerUser();

        return $customer instanceof Customer && $customer->isGuest();
    }

    /**
     * Check whether someone who actually signed in is in the session.
     *
     * This is what an account page means by "a customer is logged in": a guest holds no
     * credentials and never proved the address they typed is theirs.
     */
    public function hasAuthenticatedCustomerUser(): bool
    {
        $customer = $this->getCustomerUser();

        return $customer instanceof Customer && !$customer->isGuest();
    }

    /**
     * @return bool true if a user (either admin or customer) is logged in, false otherwise
     */
    final public function hasLoggedInUser(): bool
    {
        return $this->hasCustomerUser() || $this->hasAdminUser();
    }

    /**
     * Check if a user has at least one of the required roles.
     *
     * @param UserInterface $user  the user
     * @param array         $roles the roles
     *
     * @return bool true if the user has the required role, false otherwise
     */
    final public function hasRequiredRole(?UserInterface $user = null, array $roles = []): bool
    {
        if ($user instanceof UserInterface) {
            // Check if user's roles matches required roles
            $userRoles = $user->getRoles();

            foreach ($userRoles as $role) {
                if (\in_array($role, $roles, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    final public function isUserGranted(array $roles, array $resources, array $modules, array $accesses, UserInterface $user): bool
    {
        if (!$this->hasRequiredRole($user, $roles)) {
            return false;
        }

        if (([] === $resources && [] === $modules) || [] === $accesses) {
            return true;
        }

        if (!method_exists($user, 'getPermissions')) {
            return false;
        }

        $userPermissions = $user->getPermissions();

        if (AdminResources::SUPERADMINISTRATOR === $userPermissions) {
            return true;
        }

        foreach ($resources as $resource) {
            if ('' === $resource) {
                continue;
            }

            $resource = strtolower((string) $resource);

            if (!\array_key_exists($resource, $userPermissions)) {
                return false;
            }

            foreach ($accesses as $access) {
                if (!$userPermissions[$resource]->can($access)) {
                    return false;
                }
            }
        }

        foreach ($modules as $module) {
            if ('' === $module) {
                continue;
            }

            if (!\array_key_exists('module', $userPermissions)) {
                return false;
            }

            $module = strtolower((string) $module);

            if (!\array_key_exists($module, $userPermissions['module'])) {
                return false;
            }

            foreach ($accesses as $access) {
                if (!$userPermissions['module'][$module]->can($access)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Checks if the current user is allowed.
     */
    final public function isGranted(array $roles, array $resources, array $modules, array $accesses): bool
    {
        // Find a user which matches the required roles.
        $user = $this->checkRole($roles);

        if (!$user instanceof UserInterface) {
            return false;
        }

        return $this->isUserGranted($roles, $resources, $modules, $accesses, $user);
    }

    /**
     * look if a user has the required role.
     */
    public function checkRole(array $roles): ?UserInterface
    {
        // Find a user which matches the required roles.
        $user = $this->customerUserHoldingRoles();

        if (!$this->hasRequiredRole($user, $roles)) {
            $user = $this->getAdminUser();

            if (!$this->hasRequiredRole($user, $roles)) {
                $user = null;
            }
        }

        return $user;
    }

    /**
     * The session customer, but only when it is an account.
     *
     * A guest row answers ROLE_CUSTOMER like any other Customer — the roles come from
     * the class, not from the row — so handing it to hasRequiredRole() would open every
     * resource guarded by that role to someone who only typed an address. The guest
     * checkout puts such a row in the session on purpose, so this is the one place the
     * distinction has to be made before the roles are read.
     */
    private function customerUserHoldingRoles(): ?UserInterface
    {
        $customer = $this->getCustomerUser();

        if (!$customer instanceof Customer) {
            return null;
        }

        return $customer->isGuest() ? null : $customer;
    }

    /**
     * Sets the authenticated admin user.
     *
     * @param UserInterface $user A UserInterface, or null if no further user should be stored
     */
    public function setAdminUser(UserInterface $user): void
    {
        $user->eraseCredentials();

        $this->getSession()?->setAdminUser($user);
    }

    /**
     * Sets the authenticated customer user.
     *
     * @param UserInterface $user A UserInterface, or null if no further user should be stored
     */
    public function setCustomerUser(UserInterface $user): void
    {
        $user->eraseCredentials();

        $this->getSession()?->setCustomerUser($user);
    }

    /**
     * Clear the customer from the security context.
     */
    public function clearCustomerUser(): void
    {
        $this->getSession()?->clearCustomerUser();
    }

    /**
     * Clear the admin from the security context.
     */
    public function clearAdminUser(): void
    {
        $this->revalidatedAdminUser = null;

        $this->getSession()?->clearAdminUser();
    }
}

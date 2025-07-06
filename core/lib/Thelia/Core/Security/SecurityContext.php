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

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Customer;

/**
 * A simple security manager, in charge of checking user.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SecurityContext
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return Session
     */
    private function getSession()
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        if (!$session instanceof SessionInterface) {
            throw new LogicException('No session found.');
        }

        return $session;
    }

    /**
     * Gets the currently authenticated user in  the admin, or null if none is defined.
     *
     * @return UserInterface|null A UserInterface instance or null if no user is available
     */
    public function getAdminUser(): mixed
    {
        return $this->getSession()->getAdminUser();
    }

    /**
     * Check if an admin user is logged in.
     *
     * @return true if an admin user is logged in, false otherwise
     */
    public function hasAdminUser(): bool
    {
        return $this->getSession()->getAdminUser() !== null;
    }

    /**
     * Gets the currently authenticated customer, or null if none is defined.
     *
     * @return Customer|null A UserInterface instance or null if no user is available
     */
    public function getCustomerUser(): mixed
    {
        return $this->getSession()->getCustomerUser();
    }

    /**
     * Check if a customer user is logged in.
     *
     * @return true if a customer is logged in, false otherwise
     */
    public function hasCustomerUser(): bool
    {
        return $this->getSession()->getCustomerUser() !== null;
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
    final public function hasRequiredRole(UserInterface $user = null, array $roles = []): bool
    {
        if ($user != null) {
            // Check if user's roles matches required roles
            $userRoles = $user->getRoles();

            foreach ($userRoles as $role) {
                if (\in_array($role, $roles)) {
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

        if (($resources === [] && $modules === []) || $accesses === []) {
            return true;
        }

        if (!method_exists($user, 'getPermissions')) {
            return false;
        }

        $userPermissions = $user->getPermissions();

        if ($userPermissions === AdminResources::SUPERADMINISTRATOR) {
            return true;
        }

        foreach ($resources as $resource) {
            if ($resource === '') {
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
            if ($module === '') {
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
     *
     * @return bool
     */
    final public function isGranted(array $roles, array $resources, array $modules, array $accesses)
    {
        // Find a user which matches the required roles.
        $user = $this->checkRole($roles);

        if (null === $user) {
            return false;
        }

        return $this->isUserGranted($roles, $resources, $modules, $accesses, $user);
    }

    /**
     * look if a user has the required role.
     *
     * @return UserInterface|null
     */
    public function checkRole(array $roles)
    {
        // Find a user which matches the required roles.
        $user = $this->getCustomerUser();

        if (!$this->hasRequiredRole($user, $roles)) {
            $user = $this->getAdminUser();

            if (!$this->hasRequiredRole($user, $roles)) {
                $user = null;
            }
        }

        return $user;
    }

    /**
     * Sets the authenticated admin user.
     *
     * @param UserInterface $user A UserInterface, or null if no further user should be stored
     */
    public function setAdminUser(UserInterface $user): void
    {
        $user->eraseCredentials();

        $this->getSession()->setAdminUser($user);
    }

    /**
     * Sets the authenticated customer user.
     *
     * @param UserInterface $user A UserInterface, or null if no further user should be stored
     */
    public function setCustomerUser(UserInterface $user): void
    {
        $user->eraseCredentials();

        $this->getSession()->setCustomerUser($user);
    }

    /**
     * Clear the customer from the security context.
     */
    public function clearCustomerUser(): void
    {
        $this->getSession()->clearCustomerUser();
    }

    /**
     * Clear the admin from the security context.
     */
    public function clearAdminUser(): void
    {
        $this->getSession()->clearAdminUser();
    }
}

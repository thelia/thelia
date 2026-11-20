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

namespace Thelia\Api\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;

/**
 * The customer an account endpoint answers to, whether it is reached over HTTP with a token or
 * read in-process by a page of the theme.
 *
 * Who the caller is comes from the firewall token when there is one. A front page has no token,
 * because its customer lives in the Thelia session, so an in-process read falls back to the
 * session customer. That fallback applies only when the main request already holds its session
 * object (`hasSession(true)`, the rule of CartOwnership): a session still behind the lazy factory
 * of the request is not created here, and in CLI there is none. A token that names someone other
 * than a customer, an administrator on a front route for instance, is never replaced by the
 * session. A guest owns no account, wherever it comes from.
 */
final readonly class AccountCaller
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SecurityContext $securityContext,
        private RequestStack $requestStack,
    ) {
    }

    public function customer(): ?Customer
    {
        $token = $this->tokenStorage->getToken();

        if (null !== $token) {
            $user = $token->getUser();

            return $user instanceof Customer && !$user->isGuest() ? $user : null;
        }

        if (!$this->requestStack->getMainRequest()?->hasSession(true)) {
            return null;
        }

        $sessionCustomer = $this->securityContext->getCustomerUser();

        return $sessionCustomer instanceof Customer && !$sessionCustomer->isGuest() ? $sessionCustomer : null;
    }
}

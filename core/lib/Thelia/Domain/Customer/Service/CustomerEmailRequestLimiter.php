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

namespace Thelia\Domain\Customer\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Caps the account emails an unauthenticated visitor can make the shop send.
 *
 * Every "mail me something about this address" entry point — a new activation code,
 * a new password — takes the address from whoever is asking, so any of them can be
 * used to mail a third party on demand, repeatedly, at the expense of the shop's
 * sending reputation. Both windows are needed: the per-address one protects the
 * owner of a single mailbox, the per-client one stops a single caller from walking
 * a list of addresses.
 *
 * Callers must answer the visitor the same way whether this returned true or false,
 * otherwise the answer tells the caller whether the address has an account.
 */
readonly class CustomerEmailRequestLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.customer_email_request_per_address')]
        private RateLimiterFactoryInterface $perAddressLimiter,
        #[Autowire(service: 'limiter.customer_email_request_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Whether one more email may be sent to the given address on this visitor's behalf.
     *
     * Call this before looking the address up, so that a caller spends the same budget
     * whether or not it names an account.
     */
    public function allows(string $email): bool
    {
        // No request in a CLI context: the per-client limit simply does not apply.
        // It is consumed first so that a caller who is already over it stops eating
        // the budget of the mailbox owner, who would then be denied a legitimate resend.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp && !$this->perClientLimiter->create($clientIp)->consume()->isAccepted()) {
            return false;
        }

        return $this->perAddressLimiter->create(hash('sha256', strtolower($email)))->consume()->isAccepted();
    }
}

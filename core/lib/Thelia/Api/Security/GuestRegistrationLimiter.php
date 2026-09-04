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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Customer\EmailAddress;

/**
 * Caps how often guest accounts may be opened.
 *
 * POST /api/front/guest-customers takes no credential: it writes a customer row and
 * hands back a token for whatever address it is given, so an unauthenticated caller
 * can fill the customer table, and can probe which addresses already have an account.
 * Both windows are needed: the per-address one keeps one mailbox from being used over
 * and over as a probe, the per-client one stops a single caller from walking a list.
 *
 * Consume this before the address is looked up, so a caller spends the same budget
 * whether or not the address turns out to name an account.
 */
final readonly class GuestRegistrationLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.guest_registration_per_address')]
        private RateLimiterFactoryInterface $perAddressLimiter,
        #[Autowire(service: 'limiter.guest_registration_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(string $email): bool
    {
        // No request in a CLI context: the per-client limit simply does not apply. It
        // is consumed first so that a caller who is already over it stops eating the
        // budget of the address, whose legitimate owner would then be turned away.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp && !$this->perClientLimiter->create($clientIp)->consume()->isAccepted()) {
            return false;
        }

        // The same normalisation the registration compares addresses with, or a caller
        // buys a fresh budget by changing the case of one letter.
        return $this->perAddressLimiter
            ->create(hash('sha256', EmailAddress::normalize($email)))
            ->consume()
            ->isAccepted();
    }
}

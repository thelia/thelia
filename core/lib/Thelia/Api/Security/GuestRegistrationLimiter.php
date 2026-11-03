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
use Thelia\Domain\Customer\Service\TwoWindowRegistrationLimiter;

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
    private TwoWindowRegistrationLimiter $limiter;

    public function __construct(
        #[Autowire(service: 'limiter.guest_registration_per_address')]
        RateLimiterFactoryInterface $perAddressLimiter,
        #[Autowire(service: 'limiter.guest_registration_per_client')]
        RateLimiterFactoryInterface $perClientLimiter,
        RequestStack $requestStack,
    ) {
        $this->limiter = new TwoWindowRegistrationLimiter($perAddressLimiter, $perClientLimiter, $requestStack);
    }

    public function allows(string $email): bool
    {
        return null === $this->attempt($email);
    }

    /**
     * Counts one more guest registration for this address, from this caller: null when it is
     * allowed, otherwise the number of seconds after which the caller may try again.
     */
    public function attempt(string $email): ?int
    {
        return $this->limiter->attempt($email);
    }
}

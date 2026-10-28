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
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Customer\EmailAddress;

/**
 * Caps how often a visitor may try to register an account.
 *
 * Registering answers "This email already exists." for an address that has an account,
 * so an unauthenticated caller can read from it which addresses are registered. Both
 * windows are needed: the per-address one keeps one mailbox from being probed over and
 * over, the per-client one stops a single caller from walking a list of addresses.
 *
 * It has to be consumed before the address is looked up, and the lookup is the
 * validation of the registration (the API body, the storefront form), which runs before
 * the registration service is reached: a limiter placed in the service would be
 * consumed only by the attempts that already got their answer. Every attempt spends
 * budget, whether the address turns out to name an account or not.
 */
readonly class CustomerRegistrationLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.customer_registration_per_address')]
        private RateLimiterFactoryInterface $perAddressLimiter,
        #[Autowire(service: 'limiter.customer_registration_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Whether one more registration attempt is allowed for this address, from this caller.
     */
    public function allows(string $email): bool
    {
        return null === $this->attempt($email);
    }

    /**
     * Counts one more registration attempt for this address, from this caller: null when it is
     * allowed, otherwise the number of seconds after which the caller may try again.
     */
    public function attempt(string $email): ?int
    {
        // No request in a CLI context: the per-client limit simply does not apply. It is
        // consumed first so that a caller who is already over it stops eating the budget
        // of the address, whose legitimate owner would then be turned away.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp) {
            $perClient = $this->perClientLimiter->create($clientIp)->consume();

            if (!$perClient->isAccepted()) {
                return self::secondsToWait($perClient);
            }
        }

        // The normalisation the registration compares addresses with, or a caller buys a
        // fresh budget by changing the case of one letter.
        $perAddress = $this->perAddressLimiter
            ->create(hash('sha256', EmailAddress::normalize($email)))
            ->consume();

        return $perAddress->isAccepted() ? null : self::secondsToWait($perAddress);
    }

    private static function secondsToWait(RateLimit $refusal): int
    {
        return max(1, $refusal->getRetryAfter()->getTimestamp() - time());
    }
}

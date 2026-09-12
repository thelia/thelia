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

namespace Thelia\Domain\OrderReturn\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Model\Customer;

/**
 * Caps how often a caller may open a return, keyed on the authenticated customer
 * when known and on the client IP otherwise.
 */
readonly class ReturnRequestLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.order_return_request_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(?Customer $customer = null): bool
    {
        $key = null !== $customer?->getId()
            ? 'customer:'.$customer->getId()
            : $this->requestStack->getMainRequest()?->getClientIp();

        if (null === $key) {
            return true;
        }

        return $this->perClientLimiter->create($key)->consume()->isAccepted();
    }
}

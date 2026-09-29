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

namespace Thelia\Domain\QuickOrder\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Model\Customer;

/**
 * Caps how often one account may resolve references or add them to its cart.
 * Both count against the same budget: each of them reads prices and stock for
 * up to five hundred lines.
 */
final readonly class QuickOrderLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.quick_order_per_customer')]
        private RateLimiterFactoryInterface $perCustomerLimiter,
    ) {
    }

    public function allows(Customer $customer): bool
    {
        return $this->perCustomerLimiter->create('customer:'.$customer->getId())->consume()->isAccepted();
    }
}

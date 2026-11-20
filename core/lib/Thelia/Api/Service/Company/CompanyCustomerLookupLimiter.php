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

namespace Thelia\Api\Service\Company;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Caps the accounts an administrator looks up by e-mail address to attach them to a company: the
 * answer tells whether an address holds an account, which is worth asking for a guess at a time.
 */
final readonly class CompanyCustomerLookupLimiter
{
    private const int LIMIT = 30;

    private const string INTERVAL = '1 minute';

    private RateLimiterFactory $factory;

    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')]
        CacheItemPoolInterface $pool,
    ) {
        $this->factory = new RateLimiterFactory([
            'id' => 'company_customer_lookup',
            'policy' => 'sliding_window',
            'limit' => self::LIMIT,
            'interval' => self::INTERVAL,
        ], new CacheStorage($pool));
    }

    /**
     * Whether one more lookup may be made by this administrator, and spends it.
     */
    public function allows(string $administratorIdentifier): bool
    {
        return $this->factory->create($administratorIdentifier)->consume()->isAccepted();
    }
}

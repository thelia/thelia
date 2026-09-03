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

namespace Thelia\Core\Security\RateLimiter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The callers that are not counted: a stock feed, an order export, a search
 * indexer — work that legitimately calls faster than any browser does.
 *
 * An exemption is a hole in the protection, so it is deliberately narrow: it is
 * declared once, in the environment, by whoever runs the shop, and it is read
 * from the caller's address only. Never from anything the caller sends, which
 * would let any caller exempt itself; never from a token either, since a token
 * is a secret that rotates and would have to be pasted into the configuration
 * of every server.
 *
 * Login attempts are outside its reach by design: an integration holds a token,
 * it does not log in again and again.
 */
final readonly class RateLimitAllowlist
{
    /** @var list<string> */
    private array $ranges;

    public function __construct(
        #[Autowire('%env(THELIA_API_RATE_LIMIT_ALLOWLIST)%')]
        string $ranges,
    ) {
        $this->ranges = array_values(
            array_filter(
                array_map('trim', explode(',', $ranges)),
                static fn (string $range): bool => '' !== $range,
            ),
        );
    }

    public function exempts(?string $clientIp): bool
    {
        if (null === $clientIp || '' === $clientIp || [] === $this->ranges) {
            return false;
        }

        return IpUtils::checkIp($clientIp, $this->ranges);
    }
}

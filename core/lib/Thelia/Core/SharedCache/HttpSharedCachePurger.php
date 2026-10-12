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

namespace Thelia\Core\SharedCache;

use ApiPlatform\HttpCache\PurgerInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class HttpSharedCachePurger implements SharedCachePurgerInterface
{
    /**
     * @param list<PurgerInterface> $purgers
     */
    public function __construct(private array $purgers)
    {
    }

    public function purge(array $keys): void
    {
        if ([] === $keys) {
            return;
        }

        $failures = [];

        foreach ($this->purgers as $purger) {
            try {
                $purger->purge($keys);
            } catch (\Throwable $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        if ([] !== $failures) {
            throw new \RuntimeException(\sprintf('%d of %d proxies could not be purged: %s', \count($failures), \count($this->purgers), implode(' | ', $failures)));
        }
    }
}

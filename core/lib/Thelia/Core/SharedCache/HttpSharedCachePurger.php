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
    public function __construct(private PurgerInterface $purger)
    {
    }

    public function purge(array $keys): void
    {
        if ([] !== $keys) {
            $this->purger->purge($keys);
        }
    }
}

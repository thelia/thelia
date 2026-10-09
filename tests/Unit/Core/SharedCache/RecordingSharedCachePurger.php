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

namespace Thelia\Tests\Unit\Core\SharedCache;

use Thelia\Core\SharedCache\SharedCachePurgerInterface;

final class RecordingSharedCachePurger implements SharedCachePurgerInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function purge(array $keys): void
    {
        $this->calls[] = $keys;

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}

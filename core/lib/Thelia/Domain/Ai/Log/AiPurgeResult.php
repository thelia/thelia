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

namespace Thelia\Domain\Ai\Log;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a purge of the AI layer removed (or would remove, in a dry run).
 *
 * @internal
 */
#[Exclude]
final readonly class AiPurgeResult
{
    public function __construct(
        public int $logRows,
        public int $storedPrompts,
        public int $expiredReservations,
        public int $toolLogRows = 0,
        public int $toolArguments = 0,
        public int $expiredApprovals = 0,
        public int $decidedApprovals = 0,
    ) {
    }
}

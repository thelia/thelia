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

namespace Thelia\Domain\Ai\Budget;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * An amount held in the budget while a call runs. The ledger hands it out, the call gives it back: written
 * with the log row that replaces it, or released when the call ends without one.
 *
 * @internal
 */
#[Exclude]
final class AiBudgetHold
{
    private bool $released = false;

    public function __construct(public readonly int $id, public readonly string $usageId, public readonly string $amount)
    {
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function markReleased(): void
    {
        $this->released = true;
    }
}

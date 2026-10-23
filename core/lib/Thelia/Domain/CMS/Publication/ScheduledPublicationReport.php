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

namespace Thelia\Domain\CMS\Publication;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What one pass of the scheduled publication changed, or would have changed, and what
 * it had to leave as it was.
 */
#[Exclude]
final class ScheduledPublicationReport
{
    /** @var list<string> one line per row, "content #12 published" */
    private array $changes = [];

    /** @var list<string> one line per row left as it was, with the reason: refused by the guard, or failed on its event */
    private array $warnings = [];

    public function changed(string $type, int $id, PublicationState $state): void
    {
        $this->changes[] = \sprintf('%s #%d %s', $type, $id, $state->value);
    }

    public function refused(string $type, int $id, string $reason): void
    {
        $this->warnings[] = \sprintf('%s #%d left as it is: %s', $type, $id, $reason);
    }

    /**
     * A row whose publication event broke for a reason that is not a refusal: a listener
     * of a module, the database. The row keeps its state and is reported like a refusal,
     * so the scheduler alerts, and the next pass tries it again.
     */
    public function failed(string $type, int $id, string $reason): void
    {
        $this->warnings[] = \sprintf('%s #%d failed: %s', $type, $id, $reason);
    }

    /** @return list<string> */
    public function changes(): array
    {
        return $this->changes;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}

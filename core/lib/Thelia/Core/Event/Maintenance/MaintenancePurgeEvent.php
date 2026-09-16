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

namespace Thelia\Core\Event\Maintenance;

use Symfony\Contracts\EventDispatcher\Event;

class MaintenancePurgeEvent extends Event
{
    private array $results = [];

    /**
     * Whether the run is only reporting what it would remove.
     *
     * A listener that deletes rows has to be told, otherwise `maintenance:purge
     * --dry-run` deletes everything the core kept its hands off — which is the
     * opposite of what the option promises.
     */
    public function __construct(
        private readonly bool $dryRun = false,
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function addResult(string $message): self
    {
        $this->results[] = $message;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getResults(): array
    {
        return $this->results;
    }
}

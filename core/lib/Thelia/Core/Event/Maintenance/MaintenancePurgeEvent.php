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

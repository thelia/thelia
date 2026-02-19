<?php

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

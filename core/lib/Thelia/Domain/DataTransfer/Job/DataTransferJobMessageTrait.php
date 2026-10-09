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

namespace Thelia\Domain\DataTransfer\Job;

/**
 * What the messages of the export and import jobs share. A class using it declares a
 * promoted \$postponements property and a constructor taking the id of the row first
 * and the number of times the job was looked at again second, as postponed() and
 * forReplay() build it again that way.
 */
trait DataTransferJobMessageTrait
{
    abstract public function jobId(): int;

    abstract public function jobTable(): JobTable;

    /**
     * The job as the failed jobs screen names it: "Export #4", "Import #12".
     */
    public function describe(): string
    {
        return $this->jobTable()->describe($this->jobId());
    }

    public function postponements(): int
    {
        return $this->postponements;
    }

    public function postponed(): static
    {
        return new static($this->jobId(), $this->postponements + 1);
    }

    public function forReplay(): static
    {
        return new static($this->jobId());
    }
}

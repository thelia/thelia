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
 * What the messages of the export and import jobs share: the id of the row comes
 * first in their constructor, the number of times the job was looked at again second.
 */
trait DataTransferJobMessageTrait
{
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

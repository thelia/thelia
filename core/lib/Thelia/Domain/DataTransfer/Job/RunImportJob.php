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

use Thelia\Messenger\Message\DescribedJob;

/**
 * Runs the import described by one import_job row.
 *
 * Only the id travels: what to import and how is read from the row when the job
 * runs, so a job replayed from the failure transport runs on the row as it is then.
 */
final readonly class RunImportJob implements DataTransferJobMessage, DescribedJob
{
    public function __construct(
        public int $importJobId,
        public int $postponements = 0,
    ) {
    }

    public function jobId(): int
    {
        return $this->importJobId;
    }

    public function postponements(): int
    {
        return $this->postponements;
    }

    public function postponed(): static
    {
        return new self($this->importJobId, $this->postponements + 1);
    }

    public function describe(): string
    {
        return \sprintf('Import #%d', $this->importJobId);
    }
}

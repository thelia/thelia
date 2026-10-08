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
 * A message that runs one export or import job, by the id of its row.
 */
interface DataTransferJobMessage
{
    public function jobId(): int;

    /**
     * The job as the failed jobs screen names it: "Export #4", "Import #12".
     */
    public function describe(): string;

    /**
     * How many times the job was found running elsewhere and looked at again later.
     */
    public function postponements(): int;

    /**
     * The same message, to look at the job again later.
     */
    public function postponed(): static;
}

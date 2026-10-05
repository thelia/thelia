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
 * Runs the import described by one import_job row, on the file it recorded.
 */
final readonly class RunImportJob
{
    public function __construct(
        public int $importJobId,
    ) {
    }
}

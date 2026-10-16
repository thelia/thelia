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

namespace Thelia\Messenger\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Thelia\Messenger\JobSetAsideException;
use Thelia\Messenger\Message\UndecodableJob;

/**
 * Sends a job that cannot be read straight to the failure transport, where it is kept
 * in sight instead of being deleted.
 */
#[AsMessageHandler]
final readonly class UndecodableJobHandler
{
    public function __invoke(UndecodableJob $job): never
    {
        throw new JobSetAsideException(\sprintf('The job %s cannot be read: %s', $job->originalType, $job->reason));
    }
}

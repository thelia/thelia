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

namespace Thelia\Messenger\Message;

/**
 * A job that says what it is in one line, for the back-office list of failed jobs.
 *
 * The line names what the job is about, never its content: it is shown to the
 * administrators allowed on the background jobs, and written to the log.
 */
interface DescribedJob
{
    public function describe(): string;
}

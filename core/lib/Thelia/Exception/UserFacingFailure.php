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

namespace Thelia\Exception;

/**
 * An exception whose message is written for the administrator: the reason a job
 * gives itself (no data to export, a file it cannot read, a module that is gone).
 *
 * Any other exception may quote SQL, values, paths or host names: an administrator
 * reads that the job failed and that the details are in the server log
 * ({@see JobFailureMessage}).
 */
interface UserFacingFailure
{
}

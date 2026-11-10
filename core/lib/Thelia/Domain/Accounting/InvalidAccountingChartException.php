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

namespace Thelia\Domain\Accounting;

use Thelia\Exception\UserFacingFailure;

/**
 * A chart of accounts that cannot be read, or that lacks an account the export needs: refused as a
 * whole, never half applied. Its message names what to set, for the administrator to read.
 */
final class InvalidAccountingChartException extends \InvalidArgumentException implements UserFacingFailure
{
}

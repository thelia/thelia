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

namespace Thelia\Domain\Reporting\Mailing;

/**
 * Why a due report was not sent by a run: another process holds it, or it stopped being
 * due once locked (sent by that process meanwhile, or no recipient left to retry).
 */
enum DueReportSkip
{
    case Locked;
    case NoLongerDue;
}

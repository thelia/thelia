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

namespace Thelia\Domain\DataTransfer\Export;

/**
 * An export that can tell, in a few figures, what it holds: the opening of the e-mail a
 * report mailing sends it in.
 */
interface MailableReportInterface
{
    /**
     * Called on the export instance configured as the export run was: the same period
     * and the same language. Labels are translated to that language, values formatted.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array;
}

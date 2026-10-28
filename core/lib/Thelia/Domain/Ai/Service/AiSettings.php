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

namespace Thelia\Domain\Ai\Service;

use Thelia\Model\ConfigQuery;

/**
 * The global settings of the AI layer, kept in the `config` table: the master switch, the monthly cap all
 * usages share and the option "EU only".
 *
 * @internal
 */
class AiSettings
{
    public function isEnabled(): bool
    {
        return '0' !== trim((string) ConfigQuery::read('ai_enabled', '1'));
    }

    /**
     * @return string a positive decimal, '0' when there is no global cap
     */
    public function monthlyCap(): string
    {
        $cap = trim((string) ConfigQuery::read('ai_monthly_cap', '0'));

        return 1 === preg_match('/^\d+(\.\d+)?$/', $cap) ? $cap : '0';
    }

    public function isEuropeanUnionOnly(): bool
    {
        return '1' === trim((string) ConfigQuery::read('ai_eu_only', '0'));
    }
}

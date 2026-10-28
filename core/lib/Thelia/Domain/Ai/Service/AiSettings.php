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

use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Model\ConfigQuery;

/**
 * The global settings of the AI layer, kept in the `config` table: the master switch, the monthly cap all
 * usages share, the option "EU only" and how long the usage log is kept.
 *
 * @internal
 */
class AiSettings
{
    public const int DEFAULT_LOG_RETENTION_DAYS = 400;

    public const int DEFAULT_TOOL_APPROVAL_TTL_HOURS = 24;

    public const int DEFAULT_TOOL_TOKEN_MAX_DAYS = 365;

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

    /**
     * @return int days a row of the usage log is kept, 400 when the setting is missing or is not a positive number
     */
    public function logRetentionDays(): int
    {
        $days = trim((string) ConfigQuery::read('ai_log_retention_days', (string) self::DEFAULT_LOG_RETENTION_DAYS));

        return 1 === preg_match('/^[1-9]\d{0,5}$/', $days) ? (int) $days : self::DEFAULT_LOG_RETENTION_DAYS;
    }

    public function isEuropeanUnionOnly(): bool
    {
        return '1' === trim((string) ConfigQuery::read('ai_eu_only', '0'));
    }

    public function setEuropeanUnionOnly(bool $europeanUnionOnly): void
    {
        ConfigQuery::write('ai_eu_only', $europeanUnionOnly ? '1' : '0');
    }

    public function setEnabled(bool $enabled): void
    {
        ConfigQuery::write('ai_enabled', $enabled ? '1' : '0');
    }

    /**
     * @param string $cap a non-negative decimal like "25.00", '0' for no global cap
     *
     * @throws \Thelia\Domain\Ai\Exception\InvalidAiArgumentException when it is not a decimal
     */
    public function setMonthlyCap(string $cap): void
    {
        $cap = trim($cap);
        AiAssert::decimal($cap, 'The monthly cap');

        ConfigQuery::write('ai_monthly_cap', $cap);
    }

    /**
     * Whether the MCP server of the core answers: off until the merchant turns it on (the master switch also cuts it).
     */
    public function mcpEnabled(): bool
    {
        return '1' === trim((string) ConfigQuery::read('ai_mcp_enabled', '0'));
    }

    /**
     * @return int hours a proposal of a write tool waits for an administrator, 24 when the setting is missing or is not a positive number
     */
    public function toolApprovalTtlHours(): int
    {
        $hours = trim((string) ConfigQuery::read('ai_tool_approval_ttl_hours', (string) self::DEFAULT_TOOL_APPROVAL_TTL_HOURS));

        return 1 === preg_match('/^[1-9]\d{0,4}$/', $hours) ? (int) $hours : self::DEFAULT_TOOL_APPROVAL_TTL_HOURS;
    }

    /**
     * @return int the longest life, in days, of an MCP token, 365 when the setting is missing or is not a positive number
     */
    public function toolTokenMaxDays(): int
    {
        $days = trim((string) ConfigQuery::read('ai_tool_token_max_days', (string) self::DEFAULT_TOOL_TOKEN_MAX_DAYS));

        return 1 === preg_match('/^[1-9]\d{0,4}$/', $days) ? (int) $days : self::DEFAULT_TOOL_TOKEN_MAX_DAYS;
    }
}

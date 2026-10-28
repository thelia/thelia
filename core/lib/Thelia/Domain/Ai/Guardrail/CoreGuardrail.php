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

namespace Thelia\Domain\Ai\Guardrail;

/**
 * The deterministic guardrails the core applies to every usage. They cannot be turned off; their names
 * are reserved and appear in the verdicts of the log.
 */
enum CoreGuardrail: string
{
    /** Input size and output tokens within the limits of the usage. */
    case Limits = 'limits';
    /** Requests per actor within the rate of the usage. */
    case RateLimit = 'rate_limit';
    /** The reserved cost fits in the budget of the usage and in the global monthly cap. */
    case Budget = 'budget';
    /** Untrusted content and tool results wrapped in delimiters naming their source. */
    case UntrustedContent = 'untrusted_content';
    /** The structured output decodes and matches the schema of the request. */
    case OutputSchema = 'output_schema';
    /** HTML in the output reduced to the allowed elements. */
    case HtmlSanitizer = 'html_sanitizer';

    public static function isReserved(string $name): bool
    {
        return null !== self::tryFrom($name);
    }
}

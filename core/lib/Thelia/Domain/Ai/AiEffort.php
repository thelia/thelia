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

namespace Thelia\Domain\Ai;

/**
 * How hard a model is asked to reason, set by the merchant on a tier and, when a usage needs another one, on the
 * usage. The levels are the five of the Anthropic API; an empty setting means no effort: nothing is sent and the
 * provider keeps its own default.
 *
 * The merchant's setting applies to a call only when the module sent no native option for the same thing
 * (`providerOptions`), and never to the internal calls of the core (`core.*` usages).
 */
enum AiEffort: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case XHigh = 'xhigh';
    case Max = 'max';

    /** The prefix of the usages the core declares for itself (connection test, guardrail judge). */
    private const string INTERNAL_USAGE_PREFIX = 'core.';

    /**
     * The effort a usage runs at: the one of its own settings, else the one of its tier, none for an internal usage.
     * A stored value that is not a level counts as no value, as an unknown tier does.
     */
    public static function resolve(string $usageId, ?string $usageEffort, ?string $tierEffort): ?self
    {
        if (str_starts_with($usageId, self::INTERNAL_USAGE_PREFIX)) {
            return null;
        }

        return self::tryFrom((string) $usageEffort) ?? self::tryFrom((string) $tierEffort);
    }

    /**
     * Whether the adapter of this provider turns the effort into a native parameter. For the others the setting is
     * kept and the screens say it has no effect.
     */
    public static function isTranslatedFor(AiProvider $provider): bool
    {
        return AiProvider::Anthropic === $provider;
    }
}

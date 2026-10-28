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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Whether an optional guardrail can run at this moment, and why not when it cannot: what the back office
 * shows next to its name. It is computed from the connections and the settings, never from a call.
 *
 * @internal
 */
#[Exclude]
final readonly class GuardrailAvailability
{
    /** The check could not be made: not installed, a connection that did not answer, a model that failed. */
    public const string UNAVAILABLE = 'unavailable';

    /** No active connection of the provider the guardrail calls, or no key for it. */
    public const string NO_CONNECTION = 'no_connection';

    /** The only connections it could use are outside the EU and the merchant allows EU providers only. */
    public const string JURISDICTION = 'jurisdiction';

    /** The tier it needs has no model assigned, or the model cannot do what it needs. */
    public const string NO_MODEL = 'no_model';

    /** The merchant switched the usage the guardrail relies on off, or the whole AI layer. */
    public const string INACTIVE = 'inactive';

    private function __construct(
        public bool $usable,
        public ?string $reason,
    ) {
    }

    public static function usable(): self
    {
        return new self(true, null);
    }

    public static function unusable(string $reason): self
    {
        return new self(false, $reason);
    }
}

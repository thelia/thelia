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

namespace Thelia\Domain\Ai\Guardrail\Moderation;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;

/**
 * The outcome of looking for a connection a moderation can use: the connection, or why there is none.
 *
 * @internal
 */
#[Exclude]
final readonly class ModerationSelection
{
    private function __construct(
        public ?ModerationTarget $target,
        public ?string $reason,
    ) {
    }

    public static function of(ModerationTarget $target): self
    {
        return new self($target, null);
    }

    /**
     * @param string $reason one of the reasons of GuardrailAvailability
     */
    public static function none(string $reason): self
    {
        return new self(null, $reason);
    }

    public function availability(): GuardrailAvailability
    {
        return null === $this->target ? GuardrailAvailability::unusable((string) $this->reason) : GuardrailAvailability::usable();
    }
}

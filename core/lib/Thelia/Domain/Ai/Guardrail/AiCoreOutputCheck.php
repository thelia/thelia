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
use Thelia\Domain\Ai\Provider\AiProviderResult;

/**
 * What the deterministic output guardrails made of an answer: the answer to go on with (its HTML fields
 * sanitized) and their verdicts.
 *
 * @internal
 */
#[Exclude]
final readonly class AiCoreOutputCheck
{
    /**
     * @param list<GuardrailVerdict> $verdicts
     */
    public function __construct(
        public AiProviderResult $result,
        public array $verdicts,
    ) {
    }

    public function blocks(): bool
    {
        foreach ($this->verdicts as $verdict) {
            if ($verdict->blocks()) {
                return true;
            }
        }

        return false;
    }
}

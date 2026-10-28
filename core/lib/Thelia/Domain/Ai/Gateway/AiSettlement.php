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

namespace Thelia\Domain\Ai\Gateway;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Provider\AiProviderResult;

/**
 * What the log row of a finished call holds back for the response: its identifier, the cost, every verdict, and the
 * output guardrail that blocks, if one does.
 *
 * @internal
 */
#[Exclude]
final readonly class AiSettlement
{
    /**
     * @param list<GuardrailVerdict> $verdicts
     */
    public function __construct(
        public int $logId,
        public string $cost,
        public array $verdicts,
        public ?GuardrailVerdict $blocking,
        public AiProviderResult $result,
    ) {
    }
}

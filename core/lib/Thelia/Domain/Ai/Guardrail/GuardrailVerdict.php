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
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * What a guardrail found. The guardrail says passed or failed; the gateway applies the mode the usage
 * sets for it: a failed check in block mode stops the call, in annotate mode it is only reported.
 */
#[Exclude]
final readonly class GuardrailVerdict
{
    public function __construct(
        public string $guardrail,
        public GuardrailStage $stage,
        public bool $passed,
        public GuardrailMode $mode = GuardrailMode::Block,
        public ?string $reason = null,
    ) {
        AiAssert::token($guardrail, 'A guardrail name');

        if (!$passed && (null === $reason || '' === trim($reason))) {
            throw new InvalidAiArgumentException(\sprintf('The guardrail "%s" must say why it failed.', $guardrail));
        }
    }

    public static function pass(string $guardrail, GuardrailStage $stage): self
    {
        return new self($guardrail, $stage, true);
    }

    /**
     * @param string $reason why, for the log: never the content that was checked
     */
    public static function fail(string $guardrail, GuardrailStage $stage, string $reason): self
    {
        return new self($guardrail, $stage, false, GuardrailMode::Block, $reason);
    }

    public function withMode(GuardrailMode $mode): self
    {
        return new self($this->guardrail, $this->stage, $this->passed, $mode, $this->reason);
    }

    public function blocks(): bool
    {
        return !$this->passed && GuardrailMode::Block === $this->mode;
    }

    /**
     * The shape written to the column `ai_usage_log.guardrail_verdicts`, one entry per guardrail.
     *
     * @return array{guardrail: string, stage: string, passed: bool, mode: string, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'guardrail' => $this->guardrail,
            'stage' => $this->stage->value,
            'passed' => $this->passed,
            'mode' => $this->mode->value,
            'reason' => $this->reason,
        ];
    }
}

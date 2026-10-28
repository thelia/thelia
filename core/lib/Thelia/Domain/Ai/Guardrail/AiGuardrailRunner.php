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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Model\AiUsageSetting;

/**
 * Runs the optional guardrails of a usage, the services that implement AiGuardrailInterface, and applies the
 * mode the usage gives each of them.
 *
 * The guardrails a usage runs are those the merchant chose on its settings (a JSON object, name to mode) or,
 * when the field is empty, those the usage declares. A guardrail that is chosen and not installed, or that
 * throws, counts as a failed check: a moderation that silently does nothing is worse than a refusal.
 *
 * @internal
 */
final readonly class AiGuardrailRunner
{
    public const string TAG = 'thelia.ai.guardrail';

    /**
     * @param iterable<AiGuardrailInterface> $guardrails
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        private iterable $guardrails,
    ) {
    }

    /**
     * @return array<string, GuardrailMode> guardrail name => mode, in the order they run
     */
    public function selection(AsAiUsage $usage, ?AiUsageSetting $setting): array
    {
        $chosen = trim((string) $setting?->getGuardrails());

        if ('' === $chosen) {
            return $usage->guardrails;
        }

        $decoded = json_decode($chosen, true);

        if (!\is_array($decoded)) {
            return $usage->guardrails;
        }

        $selection = [];

        foreach ($decoded as $name => $mode) {
            $mode = \is_string($mode) ? GuardrailMode::tryFrom($mode) : null;

            if (null === $mode || CoreGuardrail::isReserved((string) $name) || !self::isName((string) $name)) {
                continue;
            }

            $selection[(string) $name] = $mode;
        }

        return $selection;
    }

    /**
     * Runs the guardrails of the selection that apply to the stage, and stops at the first one that blocks.
     *
     * @param array<string, GuardrailMode> $selection
     * @param bool                         $annotateOnly every failed check is reported and none blocks: a stream cannot take back what was sent
     *
     * @return list<GuardrailVerdict> one per guardrail that ran, the blocking one last
     */
    public function run(GuardrailStage $stage, AiRequest $request, ?string $output, array $selection, bool $annotateOnly = false): array
    {
        $installed = [];

        foreach ($this->guardrails as $guardrail) {
            $installed[$guardrail->name()] = $guardrail;
        }

        $verdicts = [];

        foreach ($selection as $name => $mode) {
            $mode = $annotateOnly ? GuardrailMode::Annotate : $mode;
            $guardrail = $installed[$name] ?? null;

            if (null === $guardrail) {
                if (GuardrailStage::Input === $stage) {
                    $verdicts[] = GuardrailVerdict::fail($name, $stage, 'guardrail_unavailable')->withMode($mode);
                }
            } elseif ($guardrail->supports($stage)) {
                $verdicts[] = $this->inspect($guardrail, $name, $stage, $request, $output, $mode);
            }

            if ([] !== $verdicts && $verdicts[array_key_last($verdicts)]->blocks()) {
                break;
            }
        }

        return $verdicts;
    }

    private function inspect(AiGuardrailInterface $guardrail, string $name, GuardrailStage $stage, AiRequest $request, ?string $output, GuardrailMode $mode): GuardrailVerdict
    {
        try {
            $verdict = $guardrail->inspect($stage, $request, $output);
        } catch (\Throwable) {
            // Never the message: it may quote what was checked.
            return GuardrailVerdict::fail($name, $stage, 'guardrail_error')->withMode($mode);
        }

        // The name and the stage are the layer's to state: the log and the exceptions rely on them.
        return new GuardrailVerdict($name, $stage, $verdict->passed, $mode, $verdict->reason);
    }

    private static function isName(string $name): bool
    {
        try {
            AiAssert::token($name, 'A guardrail name');
        } catch (InvalidAiArgumentException) {
            return false;
        }

        return true;
    }
}

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

namespace Thelia\Tests\Unit\Domain\Ai\Guardrail;

use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Guardrail\AiGuardrailInterface;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;

/**
 * A guardrail that answers what the test scripted (a null verdict throws, as a classifier that is down does)
 * and remembers what it was given.
 */
final class RecordingGuardrail implements AiGuardrailInterface
{
    public int $calls = 0;

    /** @var list<string|null> */
    public array $outputs = [];

    /** @var list<AiRequest> */
    public array $requests = [];

    /**
     * @param list<GuardrailStage> $stages
     */
    public function __construct(private readonly string $name, private readonly ?GuardrailVerdict $verdict, private readonly array $stages = [GuardrailStage::Input, GuardrailStage::Output])
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supports(GuardrailStage $stage): bool
    {
        return \in_array($stage, $this->stages, true);
    }

    public function inspect(GuardrailStage $stage, AiRequest $request, ?string $output): GuardrailVerdict
    {
        ++$this->calls;
        $this->outputs[] = $output;
        $this->requests[] = $request;

        return $this->verdict ?? throw new \RuntimeException('The classifier answered: "kill all" is violent.');
    }
}

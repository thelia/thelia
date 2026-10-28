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

use Thelia\Domain\Ai\AiRequest;

/**
 * An optional check a usage can ask for by name in AsAiUsage::$guardrails: a moderation classifier, a
 * topic judge. Implement it in a module and the core tags it (autoconfiguration); the merchant can change
 * its mode per usage. The deterministic checks of CoreGuardrail are not built on this interface.
 */
interface AiGuardrailInterface
{
    /**
     * The name a usage lists and the log shows, like `mistral_moderation`.
     */
    public function name(): string;

    public function supports(GuardrailStage $stage): bool;

    /**
     * @param string|null $output what the model produced (structured output as its JSON), null at the input stage
     */
    public function inspect(GuardrailStage $stage, AiRequest $request, ?string $output): GuardrailVerdict;
}

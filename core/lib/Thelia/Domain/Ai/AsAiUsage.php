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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * Declares a usage of the AI layer: the unit the merchant turns on, budgets, rate-limits and audits.
 *
 * Put it on the service that calls the gateway, once per usage. The core finds it on every autoconfigured
 * service, those of a module included (the module skeleton loads its classes with `autoconfigure(true)`),
 * and lists it in the register of AI usages of the back office. A request names its usage by `id`.
 *
 *     #[AsAiUsage(
 *         id: 'commerceagents.shopping_assistant',
 *         purpose: 'Answers the questions of visitors about the catalogue and fills their cart.',
 *         audience: AiAudience::Public,
 *         tier: AiTier::Fast,
 *         capabilities: [AiCapability::Tools, AiCapability::Streaming],
 *         transparency: AiTransparency::Interactive,
 *     )]
 *     final readonly class ShoppingAssistant { ... }
 */
#[Exclude]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class AsAiUsage
{
    /**
     * @param string                       $id           `<module>.<purpose>` in lower case, unique in the shop
     * @param string                       $purpose      what the usage does, in one sentence, for the register of AI usages
     * @param list<AiCapability>           $capabilities what the model must be able to do
     * @param list<string>                 $serverTools  tools run by the provider itself that a request may ask for, like `web_fetch`
     * @param array<string, GuardrailMode> $guardrails   optional guardrails (classifiers) by name, with their default mode; the
     *                                                   deterministic ones of CoreGuardrail always apply and are not listed here
     */
    public function __construct(
        public string $id,
        public string $purpose,
        public AiAudience $audience,
        public AiTier $tier,
        public array $capabilities = [],
        public AiTransparency $transparency = AiTransparency::None,
        public array $serverTools = [],
        public array $guardrails = [],
    ) {
        AiAssert::usageId($id);
        AiAssert::notBlank($purpose, 'The purpose of a usage');

        AiAssert::listOf($capabilities, AiCapability::class, 'The capabilities of a usage');

        if (\count($capabilities) !== \count(array_unique(array_map(static fn (AiCapability $capability): string => $capability->value, $capabilities)))) {
            throw new InvalidAiArgumentException(\sprintf('The usage "%s" repeats a capability.', $id));
        }

        AiAssert::listOfTokens($serverTools, 'The server tools of a usage');

        AiAssert::mapOf($guardrails, GuardrailMode::class, 'The guardrails of a usage');

        foreach (array_keys($guardrails) as $name) {
            AiAssert::token($name, 'A guardrail name');

            if (CoreGuardrail::isReserved($name)) {
                throw new InvalidAiArgumentException(\sprintf('The guardrail "%s" always applies: do not list it in the usage "%s".', $name, $id));
            }
        }
    }

    /**
     * The namespace of the identifier, by convention the module code in lower case: `theliacms`.
     */
    public function module(): string
    {
        return explode('.', $this->id, 2)[0];
    }

    public function requires(AiCapability $capability): bool
    {
        return \in_array($capability, $this->capabilities, true);
    }
}

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

namespace Thelia\Domain\Ai\Provider;

use Thelia\Domain\Ai\AiProvider;

/**
 * What a connection of each provider starts with: the endpoint to call when its base URL is empty, and the
 * environment variable that Symfony recipes and the documentation of the provider already use for its key.
 *
 * @internal
 */
final readonly class AiProviderPresets
{
    /**
     * The endpoint of the provider, ending with `/v1` for the OpenAI-compatible ones (the adapter appends
     * `/chat/completions`). Null when there is none to guess: a compatible endpoint, a self-hosted server, the
     * gateway of Thelia (its address comes with the connection).
     */
    public function defaultBaseUrl(AiProvider $provider): ?string
    {
        return match ($provider) {
            AiProvider::Mistral => 'https://api.mistral.ai',
            AiProvider::Anthropic => 'https://api.anthropic.com',
            AiProvider::OpenAi => 'https://api.openai.com',
            AiProvider::Scaleway => 'https://api.scaleway.ai/v1',
            AiProvider::OvhCloud => 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1',
            AiProvider::Albert => 'https://albert.api.etalab.gouv.fr/v1',
            AiProvider::OpenAiCompatible, AiProvider::Ollama, AiProvider::TheliaAi => null,
        };
    }

    /**
     * The variable the Flex recipe of symfony/ai-*-platform adds to the `.env` file, so that a shop that
     * already has the key there needs nothing else; null when the provider has no such convention here.
     */
    public function apiKeyEnvironmentVariable(AiProvider $provider): ?string
    {
        return match ($provider) {
            AiProvider::Mistral => 'MISTRAL_API_KEY',
            AiProvider::Anthropic => 'ANTHROPIC_API_KEY',
            AiProvider::OpenAi => 'OPENAI_API_KEY',
            default => null,
        };
    }

    /**
     * Whether a call without a key can work: a self-hosted server or a compatible endpoint may not ask for one, and
     * OVHcloud AI Endpoints answers an anonymous caller (2 requests a minute per IP and per model, 400 with a key:
     * https://docs.ovhcloud.com/en/guides/public-cloud/ai-machine-learning/ai-endpoints-getting-started).
     */
    public function keyIsOptional(AiProvider $provider): bool
    {
        return \in_array($provider, [AiProvider::Ollama, AiProvider::OpenAiCompatible, AiProvider::OvhCloud], true);
    }
}

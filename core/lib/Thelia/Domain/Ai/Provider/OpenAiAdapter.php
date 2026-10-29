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

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;

/**
 * OpenAI, on its Responses API. The bridge knows three endpoints: the default one, the European one
 * (`https://eu.api.openai.com`, data residency in the EU) and the American one. Any other address is an
 * OpenAI-compatible server: that is the other kind of connection.
 *
 * @internal
 */
final class OpenAiAdapter extends AbstractPlatformAdapter
{
    /** The Responses API refuses a `max_output_tokens` below 16 (https://developers.openai.com/api/reference/resources/responses/methods/create.md). */
    public const int MINIMUM_OUTPUT_TOKENS = 16;

    public function supports(AiProvider $provider): bool
    {
        return AiProvider::OpenAi === $provider;
    }

    protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        $region = match (strtolower((string) parse_url($baseUrl, \PHP_URL_HOST))) {
            'api.openai.com' => null,
            'eu.api.openai.com' => Factory::REGION_EU,
            'us.api.openai.com' => Factory::REGION_US,
            default => throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The AI connection "%s" is an OpenAI connection with another address: use an OpenAI-compatible connection for it.', $target->connectionCode)),
        };

        try {
            return Factory::createPlatform((string) $apiKey, $httpClient, region: $region);
        } catch (InvalidArgumentException) {
            // The bridge refuses a key that does not start with "sk-"; its message does not carry the key, this one neither.
            throw new AiProviderUnavailableException($target->connectionCode, false, \sprintf('The key of the AI connection "%s" is not an OpenAI key (they start with "sk-"): use an OpenAI-compatible connection for another service.', $target->connectionCode));
        }
    }

    protected function model(AiTarget $target, array $capabilities): Model
    {
        return new Gpt($target->model->name, $capabilities);
    }

    /**
     * OpenAI keeps what the Responses API answers for 30 days unless the call says `store: false`. The layer keeps its
     * own log and never reads that copy back, so the option is always sent as false and no module can turn it on.
     */
    protected function nativeOptions(array $native, AiTarget $target): array
    {
        $native['store'] = false;

        return $native;
    }

    protected function outputLimit(int $tokens): array
    {
        return parent::outputLimit(max(self::MINIMUM_OUTPUT_TOKENS, $tokens));
    }

    protected function refusal(array $body): ?AiProviderRefusal
    {
        return AiProviderRefusal::fromResponses($body);
    }
}

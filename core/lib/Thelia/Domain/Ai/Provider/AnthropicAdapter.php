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

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;

/**
 * Anthropic. The bridge marks the end of the system prompt, the last tool and the last user turn (or tool
 * result) for the prompt cache of Anthropic: that is the cache point the contract promises.
 *
 * @internal
 */
final class AnthropicAdapter extends AbstractPlatformAdapter
{
    public function supports(AiProvider $provider): bool
    {
        return AiProvider::Anthropic === $provider;
    }

    protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        return Factory::createPlatform((string) $apiKey, $httpClient, cacheRetention: 'short', baseUrl: $baseUrl);
    }

    protected function model(AiTarget $target, array $capabilities): Model
    {
        return new Claude($target->model->name, $capabilities);
    }

    /**
     * `effort` is written the short way in the contract (`['anthropic' => ['effort' => 'high']]`); the API reads it in `output_config`.
     *
     * Some models think by default and count the thinking in `max_tokens` (measured on claude-haiku-5-5: a 600-token
     * budget fully spent thinking, no text, stop reason `max_tokens`). A request that does not ask for thinking
     * (`['anthropic' => ['thinking' => ['type' => 'adaptive']]]`) gets none, so its budget is spent on the answer.
     */
    protected function nativeOptions(array $native): array
    {
        $native['thinking'] ??= ['type' => 'disabled'];

        if (isset($native['effort'])) {
            $outputConfig = \is_array($native['output_config'] ?? null) ? $native['output_config'] : [];
            $outputConfig['effort'] ??= $native['effort'];
            $native['output_config'] = $outputConfig;
            unset($native['effort']);
        }

        return $native;
    }

    protected function refusal(array $body): ?AiProviderRefusal
    {
        return AiProviderRefusal::fromAnthropic($body);
    }
}

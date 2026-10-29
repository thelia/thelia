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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;

/**
 * Anthropic. The bridge marks the end of the system prompt, the last tool and the last user turn (or tool
 * result) for the prompt cache of Anthropic: that is the cache point the contract promises.
 *
 * @internal
 */
final class AnthropicAdapter extends AbstractPlatformAdapter
{
    public const string THINKING_DISABLED = 'disabled';

    public const string THINKING_BETWEEN_TOOLS = 'between_tools';

    public const string THINKING_ADAPTIVE = 'adaptive';

    /** The effort asked of a model that cannot stop thinking, when the request names none. */
    public const string LOWEST_EFFORT = 'low';

    private const array THINKING_OFF = [self::THINKING_DISABLED, self::THINKING_BETWEEN_TOOLS, self::THINKING_ADAPTIVE];

    /** A model does not take back a refusal; the lifetime only keeps the pool from holding names for ever. */
    private const int REMEMBERED_FOR = 30 * 86400;

    public function __construct(
        AiHttpClientFactory $httpClients,
        AiBaseUrlValidator $baseUrls,
        AiProviderPresets $presets,
        AiMessageBagFactory $messages,
        AiResultReader $results,
        AiProviderErrorTranslator $errors,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $thinkingModes,
    ) {
        parent::__construct($httpClients, $baseUrls, $presets, $messages, $results, $errors);
    }

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
     * (`['anthropic' => ['thinking' => ['type' => 'adaptive']]]`) gets the setting that turns it off for its model:
     * `disabled` for most, `between_tools` or, for a model that refuses both, `adaptive` at the lowest effort.
     */
    protected function nativeOptions(array $native, AiTarget $target): array
    {
        if (isset($native['effort'])) {
            $outputConfig = \is_array($native['output_config'] ?? null) ? $native['output_config'] : [];
            $outputConfig['effort'] ??= $native['effort'];
            $native['output_config'] = $outputConfig;
            unset($native['effort']);
        }

        if (!isset($native['thinking'])) {
            $thinkingOff = $this->thinkingOff($target);
            $native['thinking'] = ['type' => $thinkingOff];

            if (self::THINKING_ADAPTIVE === $thinkingOff) {
                $outputConfig = \is_array($native['output_config'] ?? null) ? $native['output_config'] : [];
                $outputConfig['effort'] ??= self::LOWEST_EFFORT;
                $native['output_config'] = $outputConfig;
            }
        }

        return $native;
    }

    /**
     * A model the catalogue does not know may refuse `disabled` (HTTP 400, `invalid_request_error`) and name the
     * setting it takes instead: the call is sent once more with it, and the model is remembered so the next
     * calls go straight to it.
     */
    protected function retries(DeferredResult $deferred, AiRequest $request, AiTarget $target): bool
    {
        if (isset($request->optionsFor($target->provider)['thinking']) || self::THINKING_DISABLED !== $this->thinkingOff($target)) {
            return false;
        }

        $instead = self::settingAskedInsteadOfDisabled($deferred);

        if (null === $instead) {
            return false;
        }

        $this->thinkingModes->save($this->thinkingModes->getItem(self::memoryKey($target))->set($instead)->expiresAfter(self::REMEMBERED_FOR));

        return true;
    }

    protected function refusal(array $body): ?AiProviderRefusal
    {
        return AiProviderRefusal::fromAnthropic($body);
    }

    private function thinkingOff(AiTarget $target): string
    {
        $remembered = $this->thinkingModes->getItem(self::memoryKey($target))->get();

        if (\in_array($remembered, self::THINKING_OFF, true)) {
            return $remembered;
        }

        return \in_array($target->model->thinkingOff, self::THINKING_OFF, true) ? $target->model->thinkingOff : self::THINKING_DISABLED;
    }

    /**
     * The two refusals Anthropic sends for `disabled` (measured on 2026-10-29): "\"thinking.type.disabled\" is not
     * supported for this model. Use \"thinking.type.adaptive\" and \"output_config.effort\" [...]" and "To turn
     * thinking off on this model, send \"thinking\": {\"type\": \"between_tools\"} instead of {\"type\": \"disabled\"} [...]".
     */
    private static function settingAskedInsteadOfDisabled(DeferredResult $deferred): ?string
    {
        $raw = $deferred->getRawResult();

        if (!$raw instanceof RawHttpResult) {
            return null;
        }

        try {
            $response = $raw->getObject();

            if (400 !== $response->getStatusCode()) {
                return null;
            }

            $body = json_decode($response->getContent(false), true);
        } catch (\Throwable) {
            // The call failed some other way: reading the answer says how.
            return null;
        }

        $error = \is_array($body) && \is_array($body['error'] ?? null) ? $body['error'] : [];
        $message = \is_string($error['message'] ?? null) ? $error['message'] : '';

        if ('invalid_request_error' !== ($error['type'] ?? null)) {
            return null;
        }

        return match (true) {
            str_contains($message, '"thinking.type.disabled" is not supported') => self::THINKING_ADAPTIVE,
            str_contains($message, '{"type": "between_tools"} instead of {"type": "disabled"}') => self::THINKING_BETWEEN_TOOLS,
            default => null,
        };
    }

    private static function memoryKey(AiTarget $target): string
    {
        return 'thelia.ai.anthropic_thinking_off.'.hash('xxh128', $target->model->name);
    }
}

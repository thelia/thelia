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

namespace Thelia\Tests\Support\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Provider\AbstractPlatformAdapter;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiMessageBagFactory;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiResultReader;
use Thelia\Domain\Ai\Provider\AiTarget;
use Thelia\Domain\Ai\Provider\AnthropicAdapter;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\OllamaAdapter;
use Thelia\Domain\Ai\Provider\OpenAiAdapter;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Thelia\Domain\Ai\Provider\UntrustedContentDelimiter;
use Thelia\Domain\Webhook\WebhookUrlValidator;

/**
 * Builds the adapters over a mock transport that records what they send: nothing leaves the machine.
 */
abstract class AiProviderTestCase extends TestCase
{
    public const string KEY = 'sk-test-0123456789abcdef';

    /** A public address: the anti-SSRF client accepts it without looking a name up. */
    public const string PUBLIC_URL = 'https://93.184.216.34';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: array<string, mixed>|null, options: array<string, mixed>}> */
    protected array $sent = [];

    /**
     * @param list<MockResponse>|MockResponse $responses
     */
    protected function transport(array|MockResponse $responses): MockHttpClient
    {
        $queue = \is_array($responses) ? $responses : [$responses];

        return new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $headers = [];

            foreach ($options['headers'] ?? [] as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }

            $body = isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null;
            $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'options' => $options];

            return array_shift($queue) ?? throw new \LogicException('No more mock responses queued.');
        });
    }

    /**
     * @param class-string<AbstractPlatformAdapter> $class
     * @param array<string, string>                 $pinnedHosts
     */
    protected function adapter(string $class, MockHttpClient $transport, array $pinnedHosts = []): AbstractPlatformAdapter
    {
        return new $class(
            (new AiHttpClientFactory(60, 300))->withTransport($transport, $pinnedHosts),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiProviderPresets(),
            new AiMessageBagFactory(new UntrustedContentDelimiter()),
            new AiResultReader(),
            new AiProviderErrorTranslator(),
        );
    }

    /**
     * @return class-string<AbstractPlatformAdapter>
     */
    protected static function adapterClass(AiProvider $provider): string
    {
        return match ($provider) {
            AiProvider::Mistral => MistralAdapter::class,
            AiProvider::Anthropic => AnthropicAdapter::class,
            AiProvider::OpenAi => OpenAiAdapter::class,
            AiProvider::Ollama => OllamaAdapter::class,
            default => OpenAiCompatibleAdapter::class,
        };
    }

    protected function target(AiProvider $provider, ?string $baseUrl = self::PUBLIC_URL, ?string $key = self::KEY, bool $privateNetwork = false, string $model = 'probe-model', bool $structured = true, ?int $maxOutputTokens = 8000): AiTarget
    {
        return new AiTarget(
            1,
            'probe-connection',
            $provider,
            $baseUrl,
            $key,
            $privateNetwork,
            AiJurisdiction::EuropeanUnion,
            new AiModelSpecification($provider->value, $model, null, '1.000000', '5.000000', '0.100000', null, 'USD', 100000, $maxOutputTokens, $structured, true, false, true, AiModelSpecification::SOURCE_CATALOG),
        );
    }

    /**
     * @param list<AiMessage>|null $messages
     */
    protected function request(?array $messages = null, mixed ...$arguments): AiRequest
    {
        return new AiRequest('probe.test', $messages ?? [AiMessage::user('Bonjour')], ...$arguments);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastBody(): array
    {
        return $this->sent[array_key_last($this->sent)]['body'] ?? [];
    }
}

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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Guardrail\Moderation\AiModerationConnections;
use Thelia\Domain\Ai\Guardrail\Moderation\MistralModerationGuardrail;
use Thelia\Domain\Ai\Guardrail\Moderation\OpenAiModerationGuardrail;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiConnection;

/**
 * The moderation guardrails over the real database and a mock transport that records what leaves for the
 * moderation endpoint: nothing reaches a network, and no key is real.
 */
abstract class AiModerationTestCase extends AiGatewayTestCase
{
    /** @var list<string> */
    public const array MISTRAL_BLOCKING = ['sexual', 'hate_and_discrimination', 'violence_and_threats', 'dangerous', 'criminal', 'selfharm', 'jailbreaking'];

    public const string MISTRAL_KEY = 'mistral-test-key-0123456789';

    public const string OPENAI_KEY = 'openai-test-key-0123456789';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: array<string, mixed>|null}> */
    protected array $moderationSent = [];

    /**
     * Adds an active connection of a provider (the ones of the tests of the gateway are not touched).
     */
    protected function addConnection(AiProvider $provider, ?string $baseUrl = null, ?string $key = 'default', string $code = 'moderation', bool $active = true, string $jurisdiction = 'eu'): AiConnection
    {
        $key = 'default' === $key ? (AiProvider::Mistral === $provider ? self::MISTRAL_KEY : self::OPENAI_KEY) : $key;
        $connection = (new AiConnection())
            ->setCode($code)->setTitle($code)->setProvider($provider->value)->setJurisdiction($jurisdiction)
            ->setBaseUrl($baseUrl)->setActive($active)
            ->setApiKeyEncrypted(null === $key ? null : (new AiKeyVault(new AiKeyCipher('gateway-test-secret')))->encrypt($key));
        $connection->save();

        return $connection;
    }

    /**
     * @param list<MockResponse> $responses
     */
    protected function mistral(array $responses): MistralModerationGuardrail
    {
        return new MistralModerationGuardrail($this->connections(), $this->httpClients($responses), new AiBaseUrlValidator(new WebhookUrlValidator()), 'mistral-moderation-2603', self::MISTRAL_BLOCKING);
    }

    /**
     * @param list<MockResponse> $responses
     */
    protected function openAi(array $responses): OpenAiModerationGuardrail
    {
        return new OpenAiModerationGuardrail($this->connections(), $this->httpClients($responses), new AiBaseUrlValidator(new WebhookUrlValidator()), 'omni-moderation-latest');
    }

    /**
     * What the provider answers: the shape measured on the real answer of mistral-moderation-2603 (28/10/2026), eleven categories and their scores.
     *
     * @param list<string> $flagged
     */
    protected static function mistralAnswer(array $flagged = []): MockResponse
    {
        $names = ['sexual', 'hate_and_discrimination', 'violence_and_threats', 'dangerous', 'criminal', 'selfharm', 'health', 'financial', 'law', 'pii', 'jailbreaking'];

        return AiProviderFixtures::json(['id' => '4d71ae510af942108ef7344f903e2b88', 'model' => 'mistral-moderation-2603', 'results' => [[
            'categories' => array_combine($names, array_map(static fn (string $name): bool => \in_array($name, $flagged, true), $names)),
            'category_scores' => array_combine($names, array_map(static fn (string $name): float => \in_array($name, $flagged, true) ? 0.93 : 0.0002, $names)),
        ]]]);
    }

    /**
     * @param list<string> $flagged
     */
    protected static function openAiAnswer(array $flagged = []): MockResponse
    {
        $names = ['harassment', 'harassment/threatening', 'hate', 'hate/threatening', 'illicit', 'illicit/violent', 'self-harm', 'self-harm/intent', 'self-harm/instructions', 'sexual', 'sexual/minors', 'violence', 'violence/graphic'];

        return AiProviderFixtures::json(['id' => 'modr-0d9740456c391e43c445bf0f010940c7', 'model' => 'omni-moderation-latest', 'results' => [[
            'flagged' => [] !== $flagged,
            'categories' => array_combine($names, array_map(static fn (string $name): bool => \in_array($name, $flagged, true), $names)),
            'category_scores' => array_combine($names, array_map(static fn (string $name): float => \in_array($name, $flagged, true) ? 0.91 : 0.0001, $names)),
        ]]]);
    }

    private function connections(): AiModerationConnections
    {
        return new AiModerationConnections(new AiRoutingRepository(), new AiKeyVault(new AiKeyCipher('gateway-test-secret')), new AiProviderPresets(), new AiSettings());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function httpClients(array $responses): AiHttpClientFactory
    {
        $queue = $responses;
        $transport = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $headers = [];

            foreach ($options['headers'] ?? [] as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }

            $body = isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null;
            $this->moderationSent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

            return array_shift($queue) ?? throw new \LogicException('No more mock responses queued.');
        });

        return (new AiHttpClientFactory(60, 300))->withTransport($transport, ['api.mistral.ai' => '93.184.216.34', 'api.openai.com' => '93.184.216.34', 'eu.api.openai.com' => '93.184.216.34']);
    }
}

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

namespace Thelia\Tests\Integration\Domain\Ai;

use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiMessageBagFactory;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiResultReader;
use Thelia\Domain\Ai\Provider\AnthropicAdapter;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\UntrustedContentDelimiter;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Service\AiConnectionTester;
use Thelia\Domain\Ai\Service\AiFailureExplainer;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiConnection;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiUsageSetting;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures;

final class AiConnectionTesterTest extends IntegrationTestCase
{
    private const string KEY = 'sk-tester-0123456789abcdef';

    /** @var list<array{url: string, headers: array<string, string>, body?: mixed}> */
    private array $sent = [];

    public function testAnAnsweringConnectionIsRecordedAsChecked(): void
    {
        $connection = $this->connection();

        $error = $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        self::assertNull($error);
        self::assertNull($connection->getCheckError());
        self::assertNotNull($connection->getCheckedAt());
        self::assertCount(1, $this->sent);
        self::assertSame('https://api.mistral.ai/v1/chat/completions', $this->sent[0]['url']);
        self::assertSame('Bearer '.self::KEY, $this->sent[0]['headers']['authorization']);
    }

    public function testARefusedKeyIsRecordedWithoutTheKey(): void
    {
        $connection = $this->connection();
        $refusal = new MockResponse(json_encode(['message' => 'Unauthorized, key '.self::KEY.' is not valid']), ['http_code' => 401, 'response_headers' => ['content-type: application/json']]);

        $error = $this->tester([$refusal])->test($connection);

        self::assertNotNull($error);
        self::assertNotNull($connection->getCheckedAt());
        self::assertSame($error, $connection->getCheckError());
        self::assertStringNotContainsString(self::KEY, (string) $error);
        self::assertLessThanOrEqual(255, mb_strlen((string) $error));
    }

    public function testAnOldFailureIsClearedByANewSuccess(): void
    {
        $connection = $this->connection();
        $connection->setCheckError('earlier failure')->save();

        $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        self::assertNull($connection->getCheckError());
    }

    public function testAnAnthropicConnectionAnswersWithoutThinkingOnAModelThatRefusesDisabled(): void
    {
        $connection = $this->connection('anthropic');
        $this->assign(AiTier::Fast, $connection, 'claude-fable-5', 'anthropic');

        // The API as it answered on 2026-10-29: claude-fable-5 refuses "disabled".
        $error = $this->anthropicTester(static fn (array $body): MockResponse => AiProviderFixtures::anthropicMeasured((string) $body['model'], (string) $body['thinking']['type']))->test($connection);

        self::assertNull($error);
        self::assertNull($connection->getCheckError());
        self::assertCount(1, $this->sent);
        self::assertSame('claude-fable-5', $this->sent[0]['body']['model']);
        self::assertSame(['type' => 'adaptive'], $this->sent[0]['body']['thinking']);
        self::assertSame(16, $this->sent[0]['body']['max_tokens']);
    }

    public function testAProbeCutAtItsSixteenTokensBeforeAnyTextStillProvesTheConnection(): void
    {
        $connection = $this->connection('anthropic');
        // SIMULATED from the documentation of the Messages API: a turn cut while thinking.
        $cut = AiProviderFixtures::anthropic([['type' => 'thinking', 'thinking' => 'The user wants', 'signature' => 'EqQBCgIYAhIM']], 'max_tokens', ['input_tokens' => 15, 'output_tokens' => 16]);

        $error = $this->anthropicTester(static fn (array $body): MockResponse => $cut)->test($connection);

        self::assertNull($error);
        self::assertNull($connection->getCheckError());
    }

    public function testAConnectionWithoutKnownModelCallsNothing(): void
    {
        $connection = $this->connection('openai_compatible');

        $error = $this->tester([])->test($connection);

        self::assertStringContainsString('No model', (string) $error);
        self::assertSame([], $this->sent);
    }

    public function testTheProbeModelOfThePresetIsCalledWhenNoRangeUsesAModelOfTheConnection(): void
    {
        $this->tester([AiProviderFixtures::chat('ok')])->test($this->connection());

        self::assertSame('ministral-3b-2512', $this->sentModel(0));
    }

    public function testAModelARangeRunsOnTheConnectionWinsOverTheProbeModel(): void
    {
        $connection = $this->connection();
        $this->assign(AiTier::Advanced, $connection, 'mistral-large-latest');
        $this->assign(AiTier::Fast, $connection, 'mistral-small-latest');

        $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        self::assertSame('mistral-small-latest', $this->sentModel(0), 'the fast range comes first');
    }

    public function testAModelPinnedToAUsageOnTheConnectionIsCalledWhenNoRangeHasOne(): void
    {
        $connection = $this->connection();
        $model = $this->model('mistral-medium-latest');
        (new AiUsageSetting())->setUsageId('aitester.usage')->setPinnedConnectionId($connection->getId())->setPinnedModelId($model->getId())->save();

        $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        self::assertSame('mistral-medium-latest', $this->sentModel(0));
    }

    public function testAModelOfAnotherConnectionIsNotUsed(): void
    {
        $other = $this->connection();
        $this->assign(AiTier::Fast, $other, 'mistral-small-latest');
        $mine = (new AiConnection())->setCode('tester-mine')->setTitle('Mine')->setProvider('mistral')->setJurisdiction('eu')->setApiKeyEncrypted($this->vault()->encrypt(self::KEY));
        $mine->save();

        $this->tester([AiProviderFixtures::chat('ok')])->test($mine);

        self::assertSame('ministral-3b-2512', $this->sentModel(0));
    }

    public function testAnOllamaServerWithNoModelChosenIsNotAskedForAModelItMayNotHave(): void
    {
        // The embedded list holds models of Ollama (codegeex4 comes first): none of them is a promise that this server pulled it.
        $connection = $this->connection('ollama');

        $error = $this->tester([])->test($connection);

        self::assertSame(AiFailureExplainer::CHECK_NO_MODEL, $error);
        self::assertSame([], $this->sent);
    }

    public function testTheFailureNamesTheModelThatWasCalled(): void
    {
        $connection = $this->connection();
        $refusal = new MockResponse(json_encode(['message' => 'Invalid model: ministral-3b-2512']), ['http_code' => 400, 'response_headers' => ['content-type: application/json']]);

        $error = (string) $this->tester([$refusal])->test($connection);

        self::assertStringEndsWith(' (model: ministral-3b-2512)', $error);
        self::assertLessThanOrEqual(255, mb_strlen($error));

        $long = new MockResponse(json_encode(['message' => str_repeat('x', 600)]), ['http_code' => 400, 'response_headers' => ['content-type: application/json']]);
        $error = (string) $this->tester([$long])->test($connection);

        self::assertStringEndsWith(' (model: ministral-3b-2512)', $error, 'a long answer of the provider does not cut the name');
        self::assertLessThanOrEqual(255, mb_strlen($error));
    }

    public function testTheStoredErrorIsToldInFrenchAndInEnglishWhenShown(): void
    {
        $explainer = new AiFailureExplainer(new AiTextTranslator(new LangService(new RequestStack(), new NullLogger())));
        $noModel = $this->tester([])->test($this->connection('openai_compatible'));
        $refused = $this->tester([new MockResponse(json_encode(['message' => 'Unauthorized']), ['http_code' => 401, 'response_headers' => ['content-type: application/json']])])->test($this->connection());

        self::assertSame('Aucun modèle n\'est connu pour cette connexion : ajoutez-en un d\'abord.', $explainer->explainCheckError((string) $noModel, 'fr_FR'));
        self::assertSame('No model is known for this connection: add one first.', $explainer->explainCheckError((string) $noModel, 'en_US'));
        self::assertStringStartsWith('La connexion IA « tester-mistral » a échoué : ', $explainer->explainCheckError((string) $refused, 'fr_FR'));
        self::assertStringStartsWith('The AI connection "tester-mistral" failed: ', $explainer->explainCheckError((string) $refused, 'en_US'));
        self::assertSame('something else', $explainer->explainCheckError('something else', 'fr_FR'), 'an unknown text comes back as stored');
        self::assertSame((string) $noModel, 'No model is known for this connection: add one first.', 'the column keeps the English text');
    }

    /**
     * @param \Closure(array<string, mixed>): MockResponse $answer
     */
    private function anthropicTester(\Closure $answer): AiConnectionTester
    {
        $transport = new MockHttpClient(function (string $method, string $url, array $options) use ($answer): MockResponse {
            $body = json_decode((string) $options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->sent[] = ['url' => $url, 'headers' => [], 'body' => $body];

            return $answer($body);
        });
        $adapter = new AnthropicAdapter(
            (new AiHttpClientFactory(60, 300))->withTransport($transport, ['api.anthropic.com' => '93.184.216.34']),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiProviderPresets(),
            new AiMessageBagFactory(new UntrustedContentDelimiter()),
            new AiResultReader(),
            new AiProviderErrorTranslator(),
            new ArrayAdapter(),
        );

        return new AiConnectionTester(new AiProviderAdapters([$adapter]), new AiModelCatalog(), $this->vault(), new AiProviderPresets(), new AiRoutingRepository());
    }

    private function sentModel(int $call): ?string
    {
        return $this->sent[$call]['body']['model'] ?? null;
    }

    private function model(string $name, string $provider = 'mistral'): AiModel
    {
        $model = (new AiModel())->setProvider($provider)->setName($name)->setInputPrice('1.000000')->setOutputPrice('2.000000')->setSupportsStreaming(true)->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();

        return $model;
    }

    private function assign(AiTier $tier, AiConnection $connection, string $name, string $provider = 'mistral'): void
    {
        (new AiTierAssignment())->setTier($tier->value)->setConnectionId($connection->getId())->setModelId($this->model($name, $provider)->getId())->save();
    }

    private function connection(string $provider = 'mistral'): AiConnection
    {
        $connection = (new AiConnection())
            ->setCode('tester-'.$provider)->setTitle('Tester')->setProvider($provider)->setJurisdiction('eu')
            ->setApiKeyEncrypted($this->vault()->encrypt(self::KEY));
        $connection->save();

        return $connection;
    }

    private function vault(): AiKeyVault
    {
        return new AiKeyVault(new AiKeyCipher('tester-test-secret'));
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function tester(array $responses): AiConnectionTester
    {
        $queue = $responses;
        $transport = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $headers = [];

            foreach ($options['headers'] ?? [] as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }

            $this->sent[] = ['url' => $url, 'headers' => $headers, 'body' => isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null];

            return array_shift($queue) ?? throw new \LogicException('No more mock responses queued.');
        });
        $adapter = new MistralAdapter(
            (new AiHttpClientFactory(60, 300))->withTransport($transport, ['api.mistral.ai' => '93.184.216.34']),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiProviderPresets(),
            new AiMessageBagFactory(new UntrustedContentDelimiter()),
            new AiResultReader(),
            new AiProviderErrorTranslator(),
        );

        return new AiConnectionTester(new AiProviderAdapters([$adapter]), new AiModelCatalog(), $this->vault(), new AiProviderPresets(), new AiRoutingRepository());
    }
}

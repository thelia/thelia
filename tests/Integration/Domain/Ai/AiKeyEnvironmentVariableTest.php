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

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiMessageBagFactory;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiResultReader;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\UntrustedContentDelimiter;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiConnectionTester;
use Thelia\Domain\Ai\Service\AiFailureExplainer;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiEnvironmentKey;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiConnection;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures;

/**
 * An administrator who holds only the "admin.configuration.ai" right names the environment variable that holds
 * the API key of a connection (`api_key_env`), and chooses the host the key is sent to. A variable of the
 * application itself (APP_SECRET, the key of the vault) must never be read and sent as a bearer token.
 */
final class AiKeyEnvironmentVariableTest extends IntegrationTestCase
{
    /** An address the administrator chose, which is not the one of the provider. */
    private const string OTHER_SERVER = 'https://93.184.216.34';

    /** A secret of the server that is named like a key and is no key of the AI layer. */
    private const string SERVER_SECRET = 'STRIPE_SECRET_KEY';

    private const string SERVER_SECRET_VALUE = 'sk_live_server-secret-0123456789';

    /** @var list<array{url: string, headers: array<string, string>}> */
    private array $sent = [];

    protected function tearDown(): void
    {
        unset($_SERVER[self::SERVER_SECRET], $_SERVER['MISTRAL_API_KEY'], $_SERVER['THELIA_AI_MISTRAL_KEY']);

        parent::tearDown();
    }

    public function testTheWriterRefusesAnEnvironmentVariableThatHoldsTheApplicationSecret(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        $this->writer()->saveConnection(null, 'v3-exfil', 'Exfil', 'mistral', null, null, null, 'APP_SECRET', false, true);
    }

    /** @return iterable<string, array{string, ?string, string}> */
    public static function variablesTheWriterRefuses(): iterable
    {
        yield 'a secret of the server towards another server' => [self::SERVER_SECRET, self::OTHER_SERVER, AiEnvironmentKey::NOT_ALLOWED];
        yield 'a secret of the server towards the provider' => [self::SERVER_SECRET, null, AiEnvironmentKey::NOT_ALLOWED];
        yield 'the former application secret' => ['THELIA_AI_PREVIOUS_SECRET', null, AiEnvironmentKey::NOT_ALLOWED];
        yield 'the usual variable of another provider' => ['OPENAI_API_KEY', null, AiEnvironmentKey::NOT_ALLOWED];
        yield 'the usual variable of the provider towards another server' => ['MISTRAL_API_KEY', self::OTHER_SERVER, AiEnvironmentKey::OTHER_ADDRESS];
    }

    #[DataProvider('variablesTheWriterRefuses')]
    public function testTheWriterRefusesAVariableThatCouldCarryASecretOfTheServerAway(string $variable, ?string $baseUrl, string $message): void
    {
        try {
            $this->writer()->saveConnection(null, 'v3-exfil', 'Exfil', 'mistral', 'eu', $baseUrl, null, $variable, false, true);
            self::fail('The variable '.$variable.' must be refused.');
        } catch (InvalidAiArgumentException $refused) {
            self::assertSame($message, $refused->getMessage());
        }
    }

    public function testAnUpdateThatPointsTheUsualVariableToAnotherServerIsRefused(): void
    {
        $writer = $this->writer();
        $connection = $writer->saveConnection(null, 'v3-moved', 'Mistral', 'mistral', null, null, null, 'MISTRAL_API_KEY', false, true);

        try {
            $writer->saveConnection($connection, 'v3-moved', 'Mistral', 'mistral', 'eu', self::OTHER_SERVER, null, 'MISTRAL_API_KEY', false, true);
            self::fail('The usual variable must not follow the connection to another server.');
        } catch (InvalidAiArgumentException $refused) {
            self::assertSame(AiEnvironmentKey::OTHER_ADDRESS, $refused->getMessage());
        }

        $connection->reload();
        self::assertNull($connection->getBaseUrl());
    }

    public function testAVariableOfTheLayerGoesAnywhereAndTheUsualOneToItsProvider(): void
    {
        $writer = $this->writer();

        self::assertSame('THELIA_AI_MISTRAL_KEY', $writer->saveConnection(null, 'v3-own', 'Own', 'openai_compatible', 'eu', self::OTHER_SERVER, null, 'THELIA_AI_MISTRAL_KEY', false, true)->getApiKeyEnv());
        self::assertSame('MISTRAL_API_KEY', $writer->saveConnection(null, 'v3-usual', 'Usual', 'mistral', null, null, null, 'MISTRAL_API_KEY', false, true)->getApiKeyEnv());
        self::assertSame('MISTRAL_API_KEY', $writer->saveConnection(null, 'v3-usual-url', 'Usual', 'mistral', null, 'https://api.mistral.ai/', null, 'MISTRAL_API_KEY', false, true)->getApiKeyEnv());
    }

    /** @return iterable<string, array{string, string}> */
    public static function rowsThatPointASecretAway(): iterable
    {
        yield 'a secret of the server' => [self::SERVER_SECRET, AiEnvironmentKey::NOT_ALLOWED];
        yield 'the usual variable of the provider' => ['MISTRAL_API_KEY', AiEnvironmentKey::OTHER_ADDRESS];
    }

    /**
     * A row saved before the rule, or written without the writer: the test of the connection reads the variable
     * through the vault, which applies the same rule, and calls nothing.
     */
    #[DataProvider('rowsThatPointASecretAway')]
    public function testTheConnectionTestNeverSendsASecretOfTheServerToTheAddressOfTheConnection(string $variable, string $message): void
    {
        $_SERVER[$variable] = self::SERVER_SECRET_VALUE;
        $connection = (new AiConnection())
            ->setCode('v3-exfil-row')->setTitle('Exfil')->setProvider('mistral')->setJurisdiction('eu')
            ->setBaseUrl(self::OTHER_SERVER)->setApiKeyEnv($variable);
        $connection->save();

        $error = $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        foreach ($this->sent as $request) {
            self::assertStringNotContainsString(self::SERVER_SECRET_VALUE, implode("\n", $request['headers']), 'the secret left the server towards '.$request['url']);
        }

        self::assertSame([], $this->sent);
        self::assertSame($message, $error);
        self::assertFalse($connection->getHasKey(), 'the screen does not say a key is there');

        $explainer = new AiFailureExplainer(new AiTextTranslator(new LangService(new RequestStack(), new NullLogger())));
        self::assertStringStartsWith('La variable d\'environnement', $explainer->explainCheckError((string) $error, 'fr_FR'));
    }

    public function testTheUsualVariableIsSentToItsProvider(): void
    {
        $_SERVER['MISTRAL_API_KEY'] = 'sk-mistral-from-env-0123456789';
        $connection = (new AiConnection())
            ->setCode('v3-usual-row')->setTitle('Usual')->setProvider('mistral')->setJurisdiction('eu')
            ->setApiKeyEnv('MISTRAL_API_KEY');
        $connection->save();

        self::assertNull($this->tester([AiProviderFixtures::chat('ok')])->test($connection));
        self::assertSame('Bearer sk-mistral-from-env-0123456789', $this->sent[0]['headers']['authorization']);
        self::assertSame('https://api.mistral.ai/v1/chat/completions', $this->sent[0]['url']);
    }

    public function testTheConnectionTestNeverSendsTheApplicationSecretToTheProvider(): void
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET');
        self::assertIsString($secret);
        self::assertNotSame('', trim($secret), 'the test needs the application secret of the kernel');

        $connection = (new AiConnection())
            ->setCode('v3-exfil-row')->setTitle('Exfil')->setProvider('mistral')->setJurisdiction('eu')
            ->setApiKeyEnv('APP_SECRET');
        $connection->save();

        $this->tester([AiProviderFixtures::chat('ok')])->test($connection);

        foreach ($this->sent as $request) {
            self::assertFalse(str_contains(implode("\n", $request['headers']), trim($secret)), 'the application secret left the server as a header towards '.$request['url']);
        }
    }

    private function writer(): AiSettingsWriter
    {
        return new AiSettingsWriter(
            $this->vault(),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiUsageRegistry([], new AiUsageSettingRepository(), new AiSettings()),
            new AiSettings(),
        );
    }

    private function vault(): AiKeyVault
    {
        return new AiKeyVault(new AiKeyCipher('v3-test-secret'));
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

            $this->sent[] = ['url' => $url, 'headers' => $headers];

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

        return new AiConnectionTester(new AiProviderAdapters([$adapter]), new AiModelCatalog(), $this->vault());
    }
}

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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
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
    /** @var list<array{url: string, headers: array<string, string>}> */
    private array $sent = [];

    public function testTheWriterRefusesAnEnvironmentVariableThatHoldsTheApplicationSecret(): void
    {
        $writer = new AiSettingsWriter(
            $this->vault(),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiUsageRegistry([], new AiUsageSettingRepository(), new AiSettings()),
            new AiSettings(),
        );

        $this->expectException(InvalidAiArgumentException::class);

        $writer->saveConnection(null, 'v3-exfil', 'Exfil', 'mistral', null, null, null, 'APP_SECRET', false, true);
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

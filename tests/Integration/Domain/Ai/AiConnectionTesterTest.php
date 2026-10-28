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
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiMessageBagFactory;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiResultReader;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\UntrustedContentDelimiter;
use Thelia\Domain\Ai\Service\AiConnectionTester;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiConnection;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures;

final class AiConnectionTesterTest extends IntegrationTestCase
{
    private const string KEY = 'sk-tester-0123456789abcdef';

    /** @var list<array{url: string, headers: array<string, string>}> */
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

    public function testAConnectionWithoutKnownModelCallsNothing(): void
    {
        $connection = $this->connection('openai_compatible');

        $error = $this->tester([])->test($connection);

        self::assertStringContainsString('No model', (string) $error);
        self::assertSame([], $this->sent);
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

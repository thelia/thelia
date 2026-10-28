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

namespace Thelia\Tests\Unit\Domain\Ai\Provider;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Provider\AiNoReplayHttpClient;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class AiNoReplayHttpClientTest extends AiProviderTestCase
{
    public function testARequestThatReplaysAnEventStreamIsRefused(): void
    {
        $calls = 0;
        $client = new AiNoReplayHttpClient(new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('ok');
        }));

        self::assertSame('ok', $client->request('POST', 'https://example.test/v1/chat', ['headers' => ['Accept' => 'text/event-stream']])->getContent());
        self::assertSame('ok', $client->withOptions(['timeout' => 5])->request('POST', 'https://example.test/v1/chat')->getContent());
        self::assertSame(2, $calls);

        foreach ([['Last-Event-ID' => null], ['last-event-id' => '3'], ['Last-Event-Id: 7']] as $headers) {
            try {
                $client->request('POST', 'https://example.test/v1/chat', ['headers' => $headers]);
                self::fail('A replay must be refused.');
            } catch (TransportException $refused) {
                self::assertStringContainsString('not replayed', $refused->getMessage());
            }
        }

        self::assertSame(2, $calls);
    }

    public function testAStreamCutMidwayIsNotAskedAgainToTheProvider(): void
    {
        $cut = new MockResponse([
            'data: '.json_encode(['id' => 'c', 'object' => 'chat.completion.chunk', 'created' => 1, 'model' => 'm', 'choices' => [['index' => 0, 'delta' => ['role' => 'assistant', 'content' => 'Bon'], 'finish_reason' => null]]], \JSON_THROW_ON_ERROR)."\n\n",
            new \Exception('Connection reset by peer'),
        ], ['http_code' => 200, 'response_headers' => ['content-type' => 'text/event-stream']]);
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport([$cut, Wire::chatStream(['Bon', 'jour'])]));

        $received = [];

        try {
            foreach ($adapter->stream($this->request(), $this->target(AiProvider::OpenAiCompatible)) as $event) {
                $received[] = $event;
            }

            self::fail('The stream was cut.');
        } catch (AiProviderUnavailableException $failure) {
            self::assertTrue($failure->retryable);
        }

        self::assertCount(1, $this->sent, 'The same prompt was sent a second time: a second answer would have been billed.');
        self::assertCount(1, $received);
    }
}

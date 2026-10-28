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

use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Provider\OllamaAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class OllamaAdapterTest extends AiProviderTestCase
{
    public function testAServerOnTheLanIsReachableOnlyWhenTheConnectionAllowsIt(): void
    {
        $adapter = $this->adapter(OllamaAdapter::class, $this->transport(Wire::ollama('ok')));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::Ollama, 'http://192.168.1.20:11434', null, true));

        self::assertSame('ok', $result->text);
        self::assertSame('http://192.168.1.20:11434/api/chat', $this->sent[0]['url']);
    }

    public function testWithoutAKeyNoAuthorizationHeaderIsSent(): void
    {
        $adapter = $this->adapter(OllamaAdapter::class, $this->transport(Wire::ollama('ok')));

        $adapter->complete($this->request(), $this->target(AiProvider::Ollama, 'http://192.168.1.20:11434', null, true));

        self::assertArrayNotHasKey('authorization', $this->sent[0]['headers']);
    }

    public function testTheSameServerOnAPublicNameIsRefusedWithoutHttps(): void
    {
        $adapter = $this->adapter(OllamaAdapter::class, $this->transport(Wire::ollama('ok')));

        $this->expectException(AiProviderUnavailableException::class);

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::Ollama, 'http://93.184.216.34:11434', null));
        } finally {
            self::assertSame([], $this->sent);
        }
    }

    public function testAnUnknownModelGivesTheMessageOfTheServerAndIsNotRetried(): void
    {
        $adapter = $this->adapter(OllamaAdapter::class, $this->transport(Wire::json(['error' => 'model "nope:1b" not found, try pulling it first'], 404)));

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::Ollama, 'http://192.168.1.20:11434', null, true));
            self::fail('The call should have failed.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertFalse($exception->retryable);
            self::assertStringContainsString('HTTP 404', $exception->getMessage());
            self::assertStringContainsString('not found', $exception->getMessage());
        }
    }

    public function testAToolCallGetsAnIdentifierTheNextTurnCanAnswer(): void
    {
        $adapter = $this->adapter(OllamaAdapter::class, $this->transport(Wire::ollama('', [['function' => ['name' => 'list_categories', 'arguments' => ['limit' => 3]]]])));

        $result = $adapter->complete($this->request(tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object', 'properties' => []])]), $this->target(AiProvider::Ollama, 'http://192.168.1.20:11434', null, true));

        self::assertCount(1, $result->toolCalls);
        self::assertNotSame('', $result->toolCalls[0]->id);
        self::assertSame(['limit' => 3], $result->toolCalls[0]->arguments);
    }
}

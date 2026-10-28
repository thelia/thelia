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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class OpenAiCompatibleAdapterTest extends AiProviderTestCase
{
    private const array PINNED = ['api.scaleway.ai' => '93.184.216.34', 'oai.endpoints.kepler.ai.cloud.ovh.net' => '93.184.216.34', 'albert.api.etalab.gouv.fr' => '93.184.216.34'];

    /** @return iterable<string, array{AiProvider, string}> */
    public static function presets(): iterable
    {
        yield 'scaleway' => [AiProvider::Scaleway, 'https://api.scaleway.ai/v1/chat/completions'];
        yield 'ovh' => [AiProvider::OvhCloud, 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions'];
        yield 'albert' => [AiProvider::Albert, 'https://albert.api.etalab.gouv.fr/v1/chat/completions'];
    }

    #[DataProvider('presets')]
    public function testTheSovereignProvidersHaveTheirOwnEndpoint(AiProvider $provider, string $expected): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('ok')), self::PINNED);

        $adapter->complete($this->request(), $this->target($provider, null));

        self::assertSame($expected, $this->sent[0]['url']);
    }

    public function testACompatibleConnectionWithoutAddressHasNowhereToGo(): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('ok')));

        $this->expectException(AiProviderUnavailableException::class);

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible, null));
        } finally {
            self::assertSame([], $this->sent);
        }
    }

    public function testTheCostAnnouncedByTheGatewayIsKept(): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('ok', usageExtra: ['cost' => 0.0000123])));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible));

        self::assertSame('0.00001230', $result->announcedCost);
    }

    /** @return iterable<string, array{mixed}> */
    public static function noCost(): iterable
    {
        yield 'absent' => [null];
        yield 'zero' => [0];
        yield 'text' => ['n/a'];
        yield 'negative' => [-1.5];
    }

    #[DataProvider('noCost')]
    public function testNoUsableCostIsNoAnnouncedCost(mixed $cost): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('ok', usageExtra: null === $cost ? [] : ['cost' => $cost])));

        self::assertNull($adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible))->announcedCost);
    }

    public function testCachedTokensAreTakenOutOfTheInputTokens(): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('ok', null, 120, 9, 100)));

        self::assertEquals(new AiTokenUsage(20, 9, 100), $adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible))->tokenUsage);
    }

    /** @return iterable<string, array{string, AiFinishReason}> */
    public static function finishReasons(): iterable
    {
        yield 'stop' => ['stop', AiFinishReason::Completed];
        yield 'length' => ['length', AiFinishReason::MaxTokens];
    }

    #[DataProvider('finishReasons')]
    public function testTheFinishReasonIsMapped(string $reason, AiFinishReason $expected): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('partial', finish: $reason)));

        self::assertSame($expected, $adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible))->finishReason);
    }

    /**
     * The generic bridge of Symfony AI only knows stop and length for a text answer: any other word is a failure of
     * the call (never retried: the same request would end the same way), not a silent answer.
     */
    #[DataProvider('unsupportedReasons')]
    public function testAFinishReasonTheBridgeDoesNotKnowIsAFailureThatIsNotRetried(string $reason): void
    {
        $adapter = $this->adapter(OpenAiCompatibleAdapter::class, $this->transport(Wire::chat('partial', finish: $reason)));

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::OpenAiCompatible));
            self::fail('The call should have failed.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertFalse($exception->retryable);
            self::assertStringContainsString($reason, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedReasons(): iterable
    {
        yield 'content filter' => ['content_filter'];
        yield 'a word of the future' => ['something_new'];
    }
}

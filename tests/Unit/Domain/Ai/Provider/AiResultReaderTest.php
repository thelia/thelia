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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\Provider\AiResultReader;

final class AiResultReaderTest extends TestCase
{
    public function testWithoutAnyUsageTheTokensAreZero(): void
    {
        self::assertEquals(new AiTokenUsage(0, 0), AiResultReader::tokens(null, AiProvider::Mistral));
    }

    public function testAnthropicCountsTheCacheApartFromItsInputTokens(): void
    {
        $usage = new TokenUsage(promptTokens: 100, completionTokens: 50, cacheCreationTokens: 30, cacheReadTokens: 400);

        self::assertEquals(new AiTokenUsage(100, 50, 400, 30), AiResultReader::tokens($usage, AiProvider::Anthropic));
    }

    public function testTheOthersCountTheCacheInsideTheirPromptTokens(): void
    {
        self::assertEquals(new AiTokenUsage(20, 9, 100), AiResultReader::tokens(new TokenUsage(promptTokens: 120, completionTokens: 9, cachedTokens: 100), AiProvider::OpenAi));
        self::assertEquals(new AiTokenUsage(70, 9, 40, 10), AiResultReader::tokens(new TokenUsage(promptTokens: 120, completionTokens: 9, cacheCreationTokens: 10, cacheReadTokens: 40), AiProvider::Ollama));
    }

    public function testTheInputTokensNeverGoBelowZero(): void
    {
        self::assertSame(0, AiResultReader::tokens(new TokenUsage(promptTokens: 10, completionTokens: 1, cachedTokens: 50), AiProvider::OpenAi)->inputTokens);
    }

    /** @return iterable<string, array{string, array<string, mixed>|null}> */
    public static function texts(): iterable
    {
        yield 'plain json' => ['{"title":"A"}', ['title' => 'A']];
        yield 'with blanks around' => ["  \n{\"title\":\"A\"}\n", ['title' => 'A']];
        yield 'in a json fence' => ["```json\n{\"title\":\"A\"}\n```", ['title' => 'A']];
        yield 'in a bare fence' => ["```\n{\"title\":\"A\"}\n```", ['title' => 'A']];
        yield 'a list' => ['[1,2]', [1, 2]];
        yield 'prose' => ['Here is your title: A', null];
        yield 'a scalar' => ['42', null];
        yield 'a truncated object' => ['{"title":"A', null];
        yield 'empty' => ['', null];
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('texts')]
    public function testTheTextOfAStructuredAnswerIsDecodedWhenItCanBe(string $text, ?array $expected): void
    {
        self::assertSame($expected, AiResultReader::decode($text));
    }
}

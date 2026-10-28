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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;

final class AiConversationStateTest extends TestCase
{
    public function testTheStateOfTheProviderTravelsFromTheResponseToTheNextAssistantMessage(): void
    {
        $state = ['thinking' => [['signature' => 'EuYBCkQ', 'text' => '...']], 'container' => 'container_011']; // opaque: never read

        $message = AiMessage::fromResponse($this->response($state));

        self::assertSame($state, $message->providerState);
        self::assertSame('Let me check.', $message->content);
        self::assertSame('toolu_01', $message->toolCalls[0]->id);
    }

    public function testWithoutStateNothingIsCarried(): void
    {
        self::assertSame([], $this->response([])->providerState);
        self::assertSame([], AiMessage::fromResponse($this->response([]))->providerState);
        self::assertSame([], AiMessage::user('Hi')->providerState);
        self::assertSame([], AiMessage::assistant('Hello')->providerState);
        self::assertSame([], AiMessage::toolResult('toolu_01', 'ok')->providerState);
    }

    /**
     * @return iterable<string, array{?string, AiFinishReason}>
     */
    public static function stopReasons(): iterable
    {
        yield 'known' => ['max_tokens', AiFinishReason::MaxTokens];
        yield 'unknown to the provider list' => ['pause_turn', AiFinishReason::Other];
        yield 'new word of a provider' => ['model_context_window_exceeded', AiFinishReason::Other];
        yield 'absent' => [null, AiFinishReason::Other];
        yield 'empty' => ['', AiFinishReason::Other];
    }

    #[DataProvider('stopReasons')]
    public function testAStopReasonTheLayerDoesNotKnowIsOtherAndNeverAnException(?string $reason, AiFinishReason $expected): void
    {
        self::assertSame($expected, AiFinishReason::fromProvider($reason));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function response(array $state): AiResponse
    {
        return new AiResponse(
            text: 'Let me check.',
            data: null,
            toolCalls: [new AiToolCall('toolu_01', 'list_categories', [])],
            tokenUsage: new AiTokenUsage(10, 5),
            cost: '0.00100000',
            model: 'claude-opus-5-5',
            connection: 'anthropic-main',
            finishReason: AiFinishReason::ToolCalls,
            guardrailVerdicts: [],
            logId: 1,
            providerState: $state,
        );
    }
}

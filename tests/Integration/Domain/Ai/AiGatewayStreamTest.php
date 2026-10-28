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

use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Stream\StreamCompleted;
use Thelia\Domain\Ai\Stream\TextDelta;
use Thelia\Domain\Ai\Stream\ToolCallReceived;
use Thelia\Tests\Support\Ai\AiGatewayTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Unit\Domain\Ai\Guardrail\RecordingGuardrail;

final class AiGatewayStreamTest extends AiGatewayTestCase
{
    public function testAStreamReadToTheEndYieldsItsDeltasThenTheCompleteResponse(): void
    {
        $gateway = $this->gatewayAnswering([$this->stream(['Bon', 'jour'])]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.drafts', correlationId: 'aiprobe.draft.1')), false);

        self::assertCount(3, $events);
        self::assertInstanceOf(TextDelta::class, $events[0]);
        self::assertInstanceOf(TextDelta::class, $events[1]);
        self::assertSame(['Bon', 'jour'], [$events[0]->text, $events[1]->text]);
        $completed = $events[2];
        self::assertInstanceOf(StreamCompleted::class, $completed);
        self::assertSame('Bonjour', $completed->response->text);
        // 10 input tokens at 1.00 and 10 output tokens at 2.00 USD per million.
        self::assertSame('0.00003000', $completed->response->cost);
        self::assertSame(AiFinishReason::Completed, $completed->response->finishReason);
        self::assertNotNull($completed->response->provenance);

        $row = $this->lastLog();
        self::assertSame($row->getId(), $completed->response->logId);
        self::assertSame(['stream', 'succeeded', '0.00003000', 'aiprobe.draft.1', null], [$row->getKind(), $row->getStatus(), $row->getCost(), $row->getCorrelationId(), $row->getError()]);
        self::assertSame([10, 10], [$row->getInputTokens(), $row->getOutputTokens()]);
        self::assertSame(1, $this->logCount());
        self::assertSame(0, $this->holds());
        // The hold was the worst case: 9 input tokens and the default 16 000 output tokens.
        self::assertSame('0.03200900', $this->sent[0]['held']);
    }

    public function testTheRequestIdOfTheProviderIsOnTheCompletedResponseAndOnTheRow(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chatStream(['Bon', 'jour'], headers: ['x-request-id' => Wire::OPENAI_REQUEST_ID])]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.drafts')), false);
        $completed = $events[array_key_last($events)];

        self::assertInstanceOf(StreamCompleted::class, $completed);
        self::assertSame(Wire::OPENAI_REQUEST_ID, $completed->response->providerRequestId);
        self::assertSame(Wire::OPENAI_REQUEST_ID, $this->lastLog()->getProviderRequestId());
    }

    public function testTheChecksRunWhenTheIterationStartsNotBefore(): void
    {
        $gateway = $this->gatewayAnswering([]);
        $this->setting('aiprobe.drafts', enabled: false);

        $stream = $gateway->stream($this->request('aiprobe.drafts'));
        self::assertSame(0, $this->logCount());

        $this->expectException(InactiveAiUsageException::class);

        foreach ($stream as $ignored) {
        }
    }

    public function testAStreamOfAUsageThatDidNotDeclareStreamingIsAViolation(): void
    {
        $gateway = $this->gatewayAnswering([]);

        $this->expectException(AiUsageViolationException::class);

        foreach ($gateway->stream($this->request('aiprobe.pages')) as $ignored) {
        }
    }

    public function testACallerThatStopsReadingLeavesAnAbortedRowCountedOnWhatItReceived(): void
    {
        $gateway = $this->gatewayAnswering([$this->stream(['Bonjour tout', 'le monde', ' et bienvenue'])]);

        $events = $gateway->stream($this->request('aiprobe.drafts'));

        foreach ($events as $event) {
            self::assertInstanceOf(TextDelta::class, $event);
            break;
        }

        self::assertSame(0, $this->logCount(), 'The row is written when the generator is closed.');

        unset($events);

        $row = $this->lastLog();
        // Nothing reported yet: the input is estimated from the 26 characters of the request (9 tokens at 1.00),
        // the output from the 12 characters received (4 tokens at 2.00 USD per million).
        self::assertSame(['stream', 'aborted', 'consumer_stopped', '0.00001700'], [$row->getKind(), $row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame([9, 4], [$row->getInputTokens(), $row->getOutputTokens()]);
        self::assertSame(1, $this->logCount());
        self::assertSame(0, $this->holds());
    }

    public function testAStreamStoppedAfterItsLastEventIsNotLoggedTwice(): void
    {
        $gateway = $this->gatewayAnswering([$this->stream(['Bon', 'jour'])]);

        $events = $gateway->stream($this->request('aiprobe.drafts'));

        foreach ($events as $event) {
            if ($event instanceof StreamCompleted) {
                break;
            }
        }

        unset($events);

        self::assertSame(1, $this->logCount());
        self::assertSame('succeeded', $this->lastLog()->getStatus());
        self::assertSame(0, $this->holds());
    }

    public function testAProviderThatDropsTheConnectionMidStreamLeavesAnAbortedRowAndTheExceptionPointsAtIt(): void
    {
        $gateway = $this->gatewayAnswering([$this->stream(['Bonjour tout', ' le monde'], cutAfterDeltas: 1)]);
        $received = [];

        try {
            foreach ($gateway->stream($this->request('aiprobe.drafts')) as $event) {
                $received[] = $event;
            }

            self::fail('The provider dropped the connection.');
        } catch (AiProviderUnavailableException $cut) {
            self::assertTrue($cut->retryable);
            self::assertSame($this->lastLog()->getId(), $cut->logId);
        }

        self::assertCount(1, $received);
        $row = $this->lastLog();
        self::assertSame(['aborted', 'provider_unavailable_retryable'], [$row->getStatus(), $row->getError()]);
        // 9 estimated input tokens at 1.00 and 4 output tokens (12 characters received) at 2.00.
        self::assertSame('0.00001700', $row->getCost());
        self::assertSame(1, $this->logCount());
        self::assertSame(0, $this->holds());
        self::assertCount(1, $this->sent, 'Nothing is retried once a delta was sent.');
    }

    public function testAFailureBeforeTheFirstByteIsRetriedOnceAndTheSecondStreamIsTheResult(): void
    {
        $gateway = $this->gatewayAnswering([$this->overloaded(), $this->stream(['Bon', 'jour'])]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.drafts')), false);

        self::assertInstanceOf(StreamCompleted::class, $events[2]);
        self::assertCount(2, $this->sent);
        self::assertSame(1, $this->logCount());
        self::assertSame(0, $this->holds());
    }

    public function testAProviderStillDownAfterTheRetryIsAFailedRowOfNoCost(): void
    {
        $gateway = $this->gatewayAnswering([$this->overloaded(), $this->overloaded()]);

        try {
            foreach ($gateway->stream($this->request('aiprobe.drafts')) as $ignored) {
            }

            self::fail('The provider is down.');
        } catch (AiProviderUnavailableException $down) {
            self::assertSame($this->lastLog()->getId(), $down->logId);
        }

        self::assertCount(2, $this->sent);
        self::assertSame(['failed', 'provider_unavailable_retryable', '0.00000000', 'stream'], [$this->lastLog()->getStatus(), $this->lastLog()->getError(), $this->lastLog()->getCost(), $this->lastLog()->getKind()]);
        self::assertSame(0, $this->holds());
    }

    public function testTheOutputGuardrailsOnlyAnnotateAStream(): void
    {
        $this->installedGuardrails = [new RecordingGuardrail('probe_topic', GuardrailVerdict::fail('probe_topic', GuardrailStage::Output, 'off_topic'), [GuardrailStage::Output])];
        $this->setting('aiprobe.drafts', guardrails: '{"probe_topic":"block"}');
        $gateway = $this->gatewayAnswering([$this->stream(['Bon', 'jour'])]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.drafts')), false);
        $completed = $events[2];

        self::assertInstanceOf(StreamCompleted::class, $completed);
        self::assertSame(['off_topic'], array_map(static fn (GuardrailVerdict $verdict): ?string => $verdict->reason, $completed->response->annotations()));
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testAnInputGuardrailStillBlocksBeforeAnythingIsSent(): void
    {
        $this->installedGuardrails = [new RecordingGuardrail('probe_moderation', GuardrailVerdict::fail('probe_moderation', GuardrailStage::Input, 'violence'), [GuardrailStage::Input])];
        $this->setting('aiprobe.assistant', '5');
        $gateway = $this->gatewayAnswering([]);

        try {
            foreach ($gateway->stream($this->request('aiprobe.assistant', actor: AiActor::visitor('s'))) as $ignored) {
            }

            self::fail('The moderation blocks.');
        } catch (AiGuardrailBlockedException) {
        }

        self::assertSame(['stream', 'blocked'], [$this->lastLog()->getKind(), $this->lastLog()->getStatus()]);
        self::assertSame([], $this->sent);
    }

    public function testARefusedStreamIsLoggedAsAStream(): void
    {
        $this->setting('aiprobe.drafts', requestsPerMinute: 1);
        $gateway = $this->gatewayAnswering([$this->stream(['ok'])]);
        iterator_to_array($gateway->stream($this->request('aiprobe.drafts')), false);

        try {
            iterator_to_array($gateway->stream($this->request('aiprobe.drafts')), false);
            self::fail('One request a minute.');
        } catch (AiRateLimitExceededException) {
        }

        self::assertSame(['stream', 'rejected', 'rate_limit'], [$this->lastLog()->getKind(), $this->lastLog()->getStatus(), $this->lastLog()->getError()]);
    }

    public function testAToolCallArrivesAsAnEventAndInTheResponse(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{}');
        $gateway = $this->gatewayAnswering([Wire::chatToolStream()]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.assistant', actor: AiActor::visitor('s'), tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object'])])), false);

        self::assertInstanceOf(ToolCallReceived::class, $events[0]);
        $completed = array_pop($events);
        self::assertInstanceOf(StreamCompleted::class, $completed);
        self::assertTrue($completed->response->hasToolCalls());
        self::assertSame(AiFinishReason::ToolCalls, $completed->response->finishReason);
        self::assertNull($completed->response->provenance);
    }

    /**
     * A chat stream in one chunk per event, so that a caller that stops reading leaves the rest unread.
     *
     * @param list<string> $deltas
     */
    private function stream(array $deltas, ?int $cutAfterDeltas = null): MockResponse
    {
        $chunk = static fn (array $delta, ?string $finish = null, ?array $usage = null): string => 'data: '.json_encode(array_filter([
            'id' => 'cmpl-1', 'object' => 'chat.completion.chunk', 'created' => 1786820845, 'model' => 'probe-model-2026',
            'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]],
            'usage' => $usage,
        ], static fn (mixed $value): bool => null !== $value), \JSON_THROW_ON_ERROR)."\n\n";

        $body = [$chunk(['role' => 'assistant', 'content' => ''])];

        foreach ($deltas as $index => $text) {
            $last = $index === \count($deltas) - 1;
            $body[] = $chunk(['content' => $text], $last ? 'stop' : null, $last ? ['prompt_tokens' => 10, 'total_tokens' => 20, 'completion_tokens' => 10] : null);

            if ($index + 1 === $cutAfterDeltas) {
                $body[] = new \Exception('Connection reset by peer');

                break;
            }
        }

        $body[] = "data: [DONE]\n\n";

        return new MockResponse($body, ['http_code' => 200, 'response_headers' => ['content-type' => 'text/event-stream']]);
    }

    private function overloaded(): MockResponse
    {
        return Wire::json(['error' => ['message' => 'Overloaded', 'type' => 'api_error']], 503);
    }
}

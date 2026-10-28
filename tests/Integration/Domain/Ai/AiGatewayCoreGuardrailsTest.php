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

use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Stream\StreamCompleted;
use Thelia\Tests\Support\Ai\AiGatewayTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Unit\Domain\Ai\Guardrail\RecordingGuardrail;

/**
 * The deterministic guardrails of the answer, `output_schema` and `html_sanitizer`, applied by the gateway
 * to a call that asks for a schema.
 */
final class AiGatewayCoreGuardrailsTest extends AiGatewayTestCase
{
    private const array SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title', 'body'],
        'properties' => [
            'title' => ['type' => 'string'],
            'body' => ['type' => 'string', 'contentMediaType' => 'text/html'],
        ],
    ];

    public function testAnAnswerThatMatchesTheSchemaIsReturnedWithItsVerdictsInTheLog(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('{"title":"Hello","body":"<p>Fine</p>"}')]);

        $response = $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));

        self::assertSame(['title' => 'Hello', 'body' => '<p>Fine</p>'], $response->data);
        self::assertSame(['limits', 'rate_limit', 'budget', 'untrusted_content', 'output_schema', 'html_sanitizer'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $response->guardrailVerdicts));
        self::assertSame([], $response->annotations());

        $logged = json_decode((string) $this->lastLog()->getGuardrailVerdicts(), true);
        self::assertSame(['guardrail' => 'output_schema', 'stage' => 'output', 'passed' => true, 'mode' => 'block', 'reason' => null], $logged[4]);
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testAnAnswerThatBreaksTheSchemaIsRefusedAfterTheCallWasPaidAndPointsAtItsRow(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('{"title":"Hello"}', promptTokens: 21, completionTokens: 20)]);

        try {
            $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));
            self::fail('The answer lacks the body.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame(['output_schema', GuardrailStage::Output, 'schema_mismatch: /body: required'], [$blocked->verdict->guardrail, $blocked->verdict->stage, $blocked->verdict->reason]);
            self::assertSame($this->lastLog()->getId(), $blocked->logId);
        }

        $row = $this->lastLog();
        self::assertSame(['blocked', 'schema_mismatch: /body: required', '0.00006100'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame(0, $this->holds());
    }

    public function testAnAnswerThatIsNotJsonIsRefused(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('Sure, here is a page.')]);

        try {
            $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));
            self::fail('The answer is prose.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('invalid_json', $blocked->verdict->reason);
        }

        self::assertSame('invalid_json', $this->lastLog()->getError());
    }

    public function testAnAnswerCutByTheOutputLimitIsRefusedAsTruncated(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('{"title":"Hel', finish: 'length')]);

        try {
            $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));
            self::fail('The answer was cut.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('truncated', $blocked->verdict->reason);
        }
    }

    public function testATurnThatOnlyCallsToolsIsNotHeldToTheSchema(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('', Wire::chatToolCall())]);

        $response = $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));

        self::assertTrue($response->hasToolCalls());
        self::assertNotContains('output_schema', array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $response->guardrailVerdicts));
    }

    public function testTheHtmlTheSchemaMarksIsSanitizedInTheDataAndInTheTextAndTheVerdictSaysSo(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat((string) json_encode(['title' => '<b>kept</b>', 'body' => '<p onclick="x()">Fine</p><script>steal()</script>']))]);

        $response = $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));

        self::assertSame(['title' => '<b>kept</b>', 'body' => '<p>Fine</p>'], $response->data);
        self::assertStringNotContainsString('steal', $response->text);
        $sanitizer = array_values(array_filter($response->guardrailVerdicts, static fn (GuardrailVerdict $verdict): bool => 'html_sanitizer' === $verdict->guardrail));
        self::assertCount(1, $sanitizer);
        self::assertSame([true, GuardrailMode::Annotate, 'sanitized'], [$sanitizer[0]->passed, $sanitizer[0]->mode, $sanitizer[0]->reason]);
        self::assertStringContainsString('"reason":"sanitized"', (string) $this->lastLog()->getGuardrailVerdicts());
    }

    public function testTheOptionalOutputGuardrailsSeeTheSanitizedAnswerAndNotARefusedOne(): void
    {
        $guardrail = new RecordingGuardrail('probe_topic', GuardrailVerdict::pass('probe_topic', GuardrailStage::Output), [GuardrailStage::Output]);
        $this->installedGuardrails = [$guardrail];
        $this->setting('aiprobe.pages', guardrails: '{"probe_topic":"block"}');
        $gateway = $this->gatewayAnswering([Wire::chat('{"title":"Hello","body":"<p>Fine</p><script>steal()</script>"}'), Wire::chat('{"title":"Hello"}')]);

        $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));

        self::assertCount(1, $guardrail->outputs);
        self::assertStringNotContainsString('steal', (string) $guardrail->outputs[0]);

        try {
            $gateway->complete($this->request(outputSchema: new AiOutputSchema('page', self::SCHEMA)));
        } catch (AiGuardrailBlockedException) {
        }

        self::assertCount(1, $guardrail->outputs, 'A refused answer is not sent to a classifier.');
    }

    public function testAStreamAnnotatesAMismatchAndStillSanitizesTheFinalData(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chatStream(['{"title":', '12,"body":"<p>x</p>', '<script>steal()</script>"}'])]);

        $events = iterator_to_array($gateway->stream($this->request('aiprobe.live_pages', outputSchema: new AiOutputSchema('page', self::SCHEMA))), false);

        $completed = end($events);
        self::assertInstanceOf(StreamCompleted::class, $completed);
        $response = $completed->response;
        self::assertSame(['title' => 12, 'body' => '<p>x</p>'], $response->data);
        $annotations = $response->annotations();
        self::assertCount(1, $annotations);
        self::assertSame(['output_schema', GuardrailMode::Annotate, 'schema_mismatch: /title: type'], [$annotations[0]->guardrail, $annotations[0]->mode, $annotations[0]->reason]);
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }
}

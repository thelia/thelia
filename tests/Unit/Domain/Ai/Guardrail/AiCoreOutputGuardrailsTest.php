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

namespace Thelia\Tests\Unit\Domain\Ai\Guardrail;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Guardrail\AiCoreOutputGuardrails;
use Thelia\Domain\Ai\Guardrail\AiHtmlSanitizer;
use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Provider\AiProviderResult;

final class AiCoreOutputGuardrailsTest extends TestCase
{
    private const array SCHEMA = [
        'type' => 'object',
        'required' => ['title', 'body'],
        'properties' => [
            'title' => ['type' => 'string'],
            'body' => ['type' => 'string', 'contentMediaType' => 'text/html'],
        ],
    ];

    public function testWithoutASchemaNothingIsChecked(): void
    {
        $check = $this->guardrails()->check($this->request(null), $this->answer('free text', null), false);

        self::assertSame([], $check->verdicts);
        self::assertFalse($check->blocks());
    }

    public function testATurnThatCallsToolsHasNoAnswerYetAndIsNotChecked(): void
    {
        $result = new AiProviderResult('', null, [new AiToolCall('call_1', 'list_categories', [])], new AiTokenUsage(1, 1), AiFinishReason::ToolCalls, 'm');

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $result, false);

        self::assertSame([], $check->verdicts);
    }

    public function testAnAnswerTheProviderRefusedIsNotTakenForBrokenJson(): void
    {
        $result = new AiProviderResult('', null, [], new AiTokenUsage(5, 0), AiFinishReason::ContentFilter, 'm', refusalCategory: 'cyber');

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $result, false);

        self::assertSame([], $check->verdicts);
        self::assertFalse($check->blocks());
    }

    public function testAnAnswerThatMatchesPassesInBlockMode(): void
    {
        $data = ['title' => 'Hello', 'body' => '<p>Fine</p>'];

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $this->answer(json_encode($data, \JSON_THROW_ON_ERROR), $data), false);

        self::assertSame(['output_schema', 'html_sanitizer'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $check->verdicts));
        self::assertTrue($check->verdicts[0]->passed);
        self::assertSame([GuardrailMode::Block, GuardrailStage::Output], [$check->verdicts[0]->mode, $check->verdicts[0]->stage]);
        self::assertTrue($check->verdicts[1]->passed);
        self::assertNull($check->verdicts[1]->reason);
        self::assertFalse($check->blocks());
    }

    public function testAnAnswerThatIsNotJsonBlocks(): void
    {
        $check = $this->guardrails()->check($this->request(self::SCHEMA), $this->answer('Sure! Here is your page.', null), false);

        self::assertTrue($check->blocks());
        self::assertSame(['output_schema', 'invalid_json'], [$check->verdicts[0]->guardrail, $check->verdicts[0]->reason]);
        self::assertCount(1, $check->verdicts);
    }

    public function testAnAnswerCutByTheOutputLimitSaysSo(): void
    {
        $result = new AiProviderResult('{"title": "He', null, [], new AiTokenUsage(1, 1), AiFinishReason::MaxTokens, 'm');

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $result, false);

        self::assertSame('truncated', $check->verdicts[0]->reason);
        self::assertTrue($check->blocks());
    }

    public function testAnAnswerThatBreaksTheSchemaBlocksAndNamesTheFirstFailure(): void
    {
        $data = ['title' => 'Hello'];

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $this->answer(json_encode($data, \JSON_THROW_ON_ERROR), $data), false);

        self::assertTrue($check->blocks());
        self::assertSame('schema_mismatch: /body: required', $check->verdicts[0]->reason);
    }

    public function testAStreamCannotTakeBackWhatItSentSoItOnlyAnnotatesAndStillSanitizes(): void
    {
        $data = ['title' => 12, 'body' => '<p>x</p><script>alert(1)</script>'];

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $this->answer(json_encode($data, \JSON_THROW_ON_ERROR), $data), true);

        self::assertFalse($check->blocks());
        self::assertFalse($check->verdicts[0]->passed);
        self::assertSame(GuardrailMode::Annotate, $check->verdicts[0]->mode);
        self::assertSame('schema_mismatch: /title: type', $check->verdicts[0]->reason);
        self::assertSame('<p>x</p>', $check->result->data['body'] ?? null);
    }

    public function testTheHtmlOfAnAnswerIsSanitizedInTheDataAndInItsJsonText(): void
    {
        $data = ['title' => 'Hello', 'body' => '<p onclick="steal()">Fine</p><script>steal()</script>'];

        $check = $this->guardrails()->check($this->request(self::SCHEMA), $this->answer(json_encode($data, \JSON_THROW_ON_ERROR), $data), false);

        self::assertSame(['title' => 'Hello', 'body' => '<p>Fine</p>'], $check->result->data);
        self::assertSame($check->result->data, json_decode($check->result->text, true));
        self::assertStringNotContainsString('steal', $check->result->text);
        self::assertSame(['html_sanitizer', true, GuardrailMode::Annotate, 'sanitized'], [$check->verdicts[1]->guardrail, $check->verdicts[1]->passed, $check->verdicts[1]->mode, $check->verdicts[1]->reason]);
        self::assertFalse($check->blocks());
        // What the provider reported is kept as it came.
        self::assertSame(AiFinishReason::Completed, $check->result->finishReason);
        self::assertSame(7, $check->result->tokenUsage->inputTokens);
    }

    public function testASchemaWithoutHtmlFieldsAddsNoSanitizerVerdict(): void
    {
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];
        $data = ['title' => '<script>x</script>'];

        $check = $this->guardrails()->check($this->request($schema), $this->answer(json_encode($data, \JSON_THROW_ON_ERROR), $data), false);

        self::assertSame(['output_schema'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $check->verdicts));
        self::assertSame($data, $check->result->data);
    }

    private function guardrails(): AiCoreOutputGuardrails
    {
        return new AiCoreOutputGuardrails(new AiSchemaValidator(), new AiHtmlSanitizer());
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    private function request(?array $schema): AiRequest
    {
        return new AiRequest('aiprobe.pages', [AiMessage::user('Write')], outputSchema: null === $schema ? null : new AiOutputSchema('page', $schema));
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function answer(string $text, ?array $data): AiProviderResult
    {
        return new AiProviderResult($text, $data, [], new AiTokenUsage(7, 3), AiFinishReason::Completed, 'model');
    }
}

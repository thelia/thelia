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

use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Log\AiLogEntry;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Model\AiBudgetReservationQuery;
use Thelia\Model\AiUsageLogQuery;
use Thelia\Test\IntegrationTestCase;

final class AiUsageLogWriterTest extends IntegrationTestCase
{
    private AiBudgetLedger $ledger;

    private AiUsageLogWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new AiBudgetLedger();
        $this->writer = new AiUsageLogWriter($this->ledger);
    }

    public function testItWritesEveryColumnOfACall(): void
    {
        $expires = new \DateTimeImmutable(date('Y-m-d H:i:s', time() + 30 * 86400));
        $id = $this->writer->write(new AiLogEntry(
            usageId: 'theliacms.page_generation',
            kind: AiLogKind::Completion,
            actorType: AiActorType::Admin,
            actorHash: str_repeat('b', 64),
            status: AiUsageLogStatus::Succeeded,
            cost: '0.05421000',
            tokenUsage: new AiTokenUsage(4812, 1206, 10, 20),
            connectionCode: 'anthropic-main',
            provider: 'anthropic',
            model: 'claude-opus-5-5',
            jurisdiction: AiJurisdiction::OutsideEuropeanUnion,
            finishReason: AiFinishReason::ToolCalls,
            latencyMilliseconds: 9420,
            verdicts: [GuardrailVerdict::pass('limits', GuardrailStage::Input), GuardrailVerdict::fail('topic', GuardrailStage::Output, 'off_topic')->withMode(GuardrailMode::Annotate)],
            correlationId: 'theliacms.page.42',
            prompt: '{"messages":[]}',
            promptExpiresAt: $expires,
        ));

        $row = AiUsageLogQuery::create()->findPk($id);

        self::assertNotNull($row);
        self::assertSame('theliacms.page_generation', $row->getUsageId());
        self::assertSame('theliacms', $row->getModule());
        self::assertSame('completion', $row->getKind());
        self::assertSame('anthropic-main', $row->getConnectionCode());
        self::assertSame('anthropic', $row->getProvider());
        self::assertSame('non_eu', $row->getJurisdiction());
        self::assertSame('claude-opus-5-5', $row->getModel());
        self::assertSame([4812, 1206, 10, 20], [$row->getInputTokens(), $row->getOutputTokens(), $row->getCacheReadTokens(), $row->getCacheWriteTokens()]);
        self::assertSame('0.05421000', $row->getCost());
        self::assertSame('USD', $row->getCurrency());
        self::assertSame(['admin', str_repeat('b', 64)], [$row->getActorType(), $row->getActorHash()]);
        self::assertSame('succeeded', $row->getStatus());
        self::assertNull($row->getError());
        self::assertSame('tool_calls', $row->getFinishReason());
        self::assertSame(9420, $row->getLatencyMs());
        self::assertSame('theliacms.page.42', $row->getCorrelationId());
        self::assertSame('{"messages":[]}', $row->getPrompt());
        self::assertSame($expires->getTimestamp(), $row->getPromptExpiresAt()?->getTimestamp());
        self::assertSame([
            ['guardrail' => 'limits', 'stage' => 'input', 'passed' => true, 'mode' => 'block', 'reason' => null],
            ['guardrail' => 'topic', 'stage' => 'output', 'passed' => false, 'mode' => 'annotate', 'reason' => 'off_topic'],
        ], json_decode((string) $row->getGuardrailVerdicts(), true));
    }

    public function testARefusalIsARowOfNoCostWithItsReasonAndWithoutPrompt(): void
    {
        $id = $this->writer->write(new AiLogEntry('commerceagents.shopping_assistant', AiLogKind::Stream, AiActorType::Visitor, str_repeat('c', 64), AiUsageLogStatus::Rejected, error: 'budget'));

        $row = AiUsageLogQuery::create()->findPk($id);

        self::assertNotNull($row);
        self::assertSame(['rejected', 'budget', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame('stream', $row->getKind());
        self::assertNull($row->getPrompt());
        self::assertNull($row->getPromptExpiresAt());
        self::assertNull($row->getGuardrailVerdicts());
        self::assertNull($row->getConnectionCode());
        self::assertSame([0, 0, 0, 0], [$row->getInputTokens(), $row->getOutputTokens(), $row->getCacheReadTokens(), $row->getCacheWriteTokens()]);
    }

    public function testAnErrorLongerThanTheColumnIsCut(): void
    {
        $id = $this->writer->write(new AiLogEntry('aiprobe.writing', AiLogKind::Completion, AiActorType::System, str_repeat('d', 64), AiUsageLogStatus::Blocked, error: str_repeat('é', 400)));

        self::assertSame(255, mb_strlen((string) AiUsageLogQuery::create()->findPk($id)?->getError()));
    }

    public function testTheRowAndTheReleaseOfItsHoldHappenTogether(): void
    {
        $hold = $this->ledger->reserve('aiprobe.writing', '0.50000000', '1', '0');
        $other = $this->ledger->reserve('aiprobe.writing', '0.10000000', '1', '0');

        $this->writer->write(new AiLogEntry('aiprobe.writing', AiLogKind::Completion, AiActorType::System, str_repeat('e', 64), AiUsageLogStatus::Succeeded, cost: '0.40000000'), $hold);

        self::assertTrue($hold->isReleased());
        self::assertFalse($other->isReleased());
        self::assertNull(AiBudgetReservationQuery::create()->findPk($hold->id));
        self::assertNotNull(AiBudgetReservationQuery::create()->findPk($other->id));
    }

    public function testAnExternalCostIsARowWithItsServiceAndOperation(): void
    {
        $id = $this->writer->write(new AiLogEntry('pricewatch.offer_judgement', AiLogKind::External, AiActorType::System, str_repeat('f', 64), AiUsageLogStatus::Succeeded, cost: '0.00009870', tokenUsage: new AiTokenUsage(2350, 0), provider: 'typesafe', model: 'jev-latest', jurisdiction: AiJurisdiction::OutsideEuropeanUnion));

        $row = AiUsageLogQuery::create()->findPk($id);

        self::assertSame(['pricewatch', 'external', 'typesafe', 'jev-latest', null], [$row?->getModule(), $row?->getKind(), $row?->getProvider(), $row?->getModel(), $row?->getConnectionCode()]);
    }
}

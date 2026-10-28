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

use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiCostRecorderInterface;
use Thelia\Domain\Ai\AiExternalCost;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Gateway\AiExternalCostRecorder;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\Support\Ai\AiGatewayTestCase;

/**
 * What a module gets to account for a service it pays itself: the same checks as the gateway before the call,
 * the same log and the same budget after it.
 */
final class AiExternalCostRecorderTest extends AiGatewayTestCase
{
    private AiExternalCostRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = new AiUsageSettingRepository();
        $globalSettings = new AiSettings();
        $registry = new AiUsageRegistry(
            ['aiprobe.pages' => 'AiProbeModule\\ProbeGateway', 'aiprobe.assistant' => 'AiProbeModule\\ProbeGateway', 'aiprobe.scoring' => 'AiProbeModule\\ProbeGateway', 'aiprobe.drafts' => 'AiProbeModule\\ProbeGateway'], // @phpstan-ignore argument.type (the fixture class is not known to the analysis)
            $settings,
            $globalSettings,
        );

        $this->recorder = new AiExternalCostRecorder($registry, $settings, $globalSettings, $this->ledger, new AiUsageLogWriter($this->ledger), $this->resolver, $this->hasher);
    }

    public function testTheContainerGivesTheRecorderOfTheCore(): void
    {
        self::assertInstanceOf(AiExternalCostRecorder::class, $this->getService(AiCostRecorderInterface::class));
    }

    public function testAnUnknownUsageIsRefusedByBothCalls(): void
    {
        try {
            $this->recorder->authorize('nobody.nothing', 'typesafe', AiJurisdiction::EuropeanUnion);
            self::fail('An undeclared usage is refused.');
        } catch (UnknownAiUsageException) {
        }

        $this->expectException(UnknownAiUsageException::class);
        $this->recorder->record(new AiExternalCost('nobody.nothing', 'typesafe', 'jev-latest', '0.1'));
    }

    public function testAnInactiveUsageIsRefusedBeforeTheCall(): void
    {
        $this->setting('aiprobe.scoring', null, enabled: false);

        $this->expectException(InactiveAiUsageException::class);
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion);
    }

    public function testTheGlobalSwitchStopsExternalServicesToo(): void
    {
        ConfigQuery::write('ai_enabled', '0');

        $this->expectException(InactiveAiUsageException::class);
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion);
    }

    public function testAServiceOutsideTheEuIsAllowedWhileTheOptionIsOff(): void
    {
        $this->recorder->authorize('aiprobe.scoring', 'dataforseo', AiJurisdiction::OutsideEuropeanUnion);
        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'dataforseo', 'serp', '0.002'));

        self::assertSame('non_eu', $this->lastLog()->getJurisdiction());
    }

    public function testAServiceOutsideTheEuIsRefusedAndLoggedWhenTheOptionIsOn(): void
    {
        ConfigQuery::write('ai_eu_only', '1');

        try {
            $this->recorder->authorize('aiprobe.scoring', 'dataforseo', AiJurisdiction::OutsideEuropeanUnion, '1');
            self::fail('The merchant asked for the EU only.');
        } catch (AiJurisdictionRefusedException $refusal) {
            self::assertSame(AiJurisdiction::OutsideEuropeanUnion, $refusal->jurisdiction);
        }

        $row = $this->lastLog();
        self::assertSame(['rejected', 'jurisdiction', 'dataforseo', 'non_eu', '0.00000000', 'external'], [$row->getStatus(), $row->getError(), $row->getProvider(), $row->getJurisdiction(), $row->getCost(), $row->getKind()]);
        self::assertSame(0, $this->holds());
    }

    public function testAServiceInTheEuPassesWhenTheOptionIsOn(): void
    {
        ConfigQuery::write('ai_eu_only', '1');

        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '1');

        self::assertSame(1, $this->holds());
    }

    public function testAProviderNameTheCostWouldRefuseIsRefusedBeforeAnythingIsHeld(): void
    {
        $provider = str_repeat('p', AiExternalCost::PROVIDER_MAXIMUM_LENGTH + 1);

        try {
            $this->recorder->authorize('aiprobe.scoring', $provider, AiJurisdiction::EuropeanUnion, '0.5');
            self::fail('A provider name longer than the cost allows is refused.');
        } catch (InvalidAiArgumentException) {
        }

        self::assertSame(0, $this->holds());
    }

    public function testTheHighestCostOfTheCallIsHeldUntilTheCostIsRecorded(): void
    {
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.5');
        self::assertSame(1, $this->holds());

        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'typesafe', 'jev-latest', '0.1'));

        self::assertSame(0, $this->holds());
        self::assertSame(1, $this->logCount());
    }

    public function testTheBudgetOfTheUsageRefusesACallThatMayCrossIt(): void
    {
        $this->setting('aiprobe.scoring', '1');
        $this->spend('aiprobe.scoring', '0.90000000');

        try {
            $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.2');
            self::fail('The budget cannot take it.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertFalse($refusal->globalCap);
        }

        $row = $this->lastLog();
        self::assertSame(['rejected', 'budget', 'external'], [$row->getStatus(), $row->getError(), $row->getKind()]);
        self::assertSame(0, $this->holds());
    }

    public function testTheGlobalCapRefusesToo(): void
    {
        ConfigQuery::write('ai_monthly_cap', '2');
        $this->spend('aiprobe.pages', '1.95000000');

        try {
            $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.1');
            self::fail('The cap cannot take it.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertTrue($refusal->globalCap);
        }

        self::assertSame('budget_global', $this->lastLog()->getError());
    }

    public function testTwoPendingCallsHoldTwiceAndTheSecondOneCanBeRefused(): void
    {
        $this->setting('aiprobe.scoring', '1');
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.6');

        $this->expectException(AiBudgetExceededException::class);
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.6');
    }

    public function testTheCostIsWrittenAsAnExternalRowWhatEverTheBudget(): void
    {
        $this->setting('aiprobe.scoring', '1');
        $this->spend('aiprobe.scoring', '1.00000000');

        $logId = $this->recorder->record(new AiExternalCost(
            'aiprobe.scoring',
            'typesafe',
            'jev-latest',
            '0.00009870',
            tokenUsage: new AiTokenUsage(120, 0),
            latencyMilliseconds: 85,
            correlationId: 'run-42',
            actor: AiActor::admin(7),
        ));

        $row = $this->lastLog();
        self::assertSame($logId, $row->getId());
        self::assertSame(['aiprobe.scoring', 'aiprobe', 'external', 'typesafe', 'jev-latest', '0.00009870', 'succeeded', 85, 'run-42', 120], [
            $row->getUsageId(), $row->getModule(), $row->getKind(), $row->getProvider(), $row->getModel(), $row->getCost(), $row->getStatus(), $row->getLatencyMs(), $row->getCorrelationId(), $row->getInputTokens(),
        ]);
        self::assertSame('admin', $row->getActorType());
        self::assertSame($this->hasher->hash(AiActor::admin(7)), $row->getActorHash());
    }

    public function testAFailedCallIsRecordedWithItsStatusAndTheJurisdictionOfTheAuthorization(): void
    {
        $this->recorder->authorize('aiprobe.scoring', 'dataforseo', AiJurisdiction::OutsideEuropeanUnion, '0.1');
        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'dataforseo', 'serp', '0', status: AiUsageLogStatus::Failed));

        $row = $this->lastLog();
        self::assertSame(['failed', 'non_eu', '0.00000000'], [$row->getStatus(), $row->getJurisdiction(), $row->getCost()]);
        self::assertSame(0, $this->holds());
    }

    public function testWithoutAnActorTheResolverSaysWhoPays(): void
    {
        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'typesafe', 'jev-latest', '0.1'));

        self::assertSame($this->hasher->hash(AiActor::visitor('resolved-visitor')), $this->lastLog()->getActorHash());
    }

    public function testTheRecordedCostCountsInTheBudgetOfTheNextCall(): void
    {
        $this->setting('aiprobe.scoring', '1');
        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'typesafe', 'jev-latest', '0.95'));

        $this->expectException(AiBudgetExceededException::class);
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.1');
    }

    public function testTwoServicesKeepTheirOwnHolds(): void
    {
        $this->recorder->authorize('aiprobe.scoring', 'typesafe', AiJurisdiction::EuropeanUnion, '0.5');
        $this->recorder->authorize('aiprobe.scoring', 'dataforseo', AiJurisdiction::OutsideEuropeanUnion, '0.5');

        $this->recorder->record(new AiExternalCost('aiprobe.scoring', 'dataforseo', 'serp', '0.1'));

        self::assertSame(1, $this->holds());
        self::assertSame('non_eu', $this->lastLog()->getJurisdiction());
    }
}

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
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\Support\Ai\AiGatewayTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Unit\Domain\Ai\Guardrail\RecordingGuardrail;

final class AiGatewayCompleteTest extends AiGatewayTestCase
{
    public function testTheRequestIdOfTheProviderIsOnTheResponseAndOnTheRow(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour', headers: ['x-request-id' => Wire::OPENAI_REQUEST_ID])]);

        $response = $gateway->complete($this->request());

        self::assertSame(Wire::OPENAI_REQUEST_ID, $response->providerRequestId);
        self::assertSame(Wire::OPENAI_REQUEST_ID, $this->lastLog()->getProviderRequestId());
    }

    public function testARefusalOfTheProviderIsAnAnswerThatSaysSoNotAFailure(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('', messageExtra: ['refusal' => 'I cannot help with that.'], headers: ['x-request-id' => Wire::OPENAI_REQUEST_ID])]);

        $response = $gateway->complete($this->request());

        self::assertSame(AiFinishReason::ContentFilter, $response->finishReason);
        self::assertSame('I cannot help with that.', $response->refusalDetail);
        self::assertNull($response->refusalCategory);
        self::assertSame('content_filter', $this->lastLog()->getFinishReason());
        self::assertSame(Wire::OPENAI_REQUEST_ID, $this->lastLog()->getProviderRequestId());
    }

    public function testACallReturnsTheAnswerItsCostAndTheRowItWrote(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour', promptTokens: 21, completionTokens: 20)]);

        $response = $gateway->complete($this->request(correlationId: 'aiprobe.page.42'));

        // 21 input tokens at 1.00 and 20 output tokens at 2.00 USD per million.
        self::assertSame('0.00006100', $response->cost);
        self::assertSame(['Bonjour', 'USD', 'probe-main', 'probe-model-2026', AiFinishReason::Completed], [$response->text, $response->currency, $response->connection, $response->model, $response->finishReason]);
        self::assertSame([21, 20], [$response->tokenUsage->inputTokens, $response->tokenUsage->outputTokens]);
        self::assertSame(['limits', 'rate_limit', 'budget', 'untrusted_content'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $response->guardrailVerdicts));
        self::assertSame([], $response->annotations());

        $row = $this->lastLog();
        self::assertSame($row->getId(), $response->logId);
        self::assertSame(['aiprobe.pages', 'aiprobe', 'completion', 'probe-main', 'openai_compatible', 'probe-model-2026', 'eu'], [$row->getUsageId(), $row->getModule(), $row->getKind(), $row->getConnectionCode(), $row->getProvider(), $row->getModel(), $row->getJurisdiction()]);
        self::assertSame(['succeeded', 'completed', '0.00006100', 'USD', null, 'aiprobe.page.42'], [$row->getStatus(), $row->getFinishReason(), $row->getCost(), $row->getCurrency(), $row->getError(), $row->getCorrelationId()]);
        self::assertSame([21, 20, 0, 0], [$row->getInputTokens(), $row->getOutputTokens(), $row->getCacheReadTokens(), $row->getCacheWriteTokens()]);
        self::assertSame(['admin', $this->hasher->hash(AiActor::admin(7))], [$row->getActorType(), $row->getActorHash()]);
        self::assertNotNull($row->getLatencyMs());
        self::assertNull($row->getPrompt());
        self::assertNull($row->getPromptExpiresAt());
        self::assertSame('limits', json_decode((string) $row->getGuardrailVerdicts(), true)[0]['guardrail']);
        self::assertStringNotContainsString('sk-probe', json_encode($row->toArray(), \JSON_THROW_ON_ERROR));
        self::assertCount(1, $this->sent);
    }

    public function testACostIsLabelledWithTheCurrencyOfThePricesOfTheModel(): void
    {
        $this->model->setCurrency('EUR')->save();
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour', promptTokens: 21, completionTokens: 20)]);

        $response = $gateway->complete($this->request());

        self::assertSame('0.00006100', $response->cost);
        self::assertSame('EUR', $response->currency);
        self::assertSame('EUR', $this->lastLog()->getCurrency());
    }

    public function testTheHoldOfTheBudgetCarriesTheCurrencyOfThePricesOfTheModel(): void
    {
        $this->model->setCurrency('EUR')->save();
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour', promptTokens: 21, completionTokens: 20)]);

        $gateway->complete($this->request());

        self::assertSame('EUR', $this->sent[0]['heldCurrency']);
    }

    public function testTheBudgetHoldsTheWorstCaseDuringTheCallAndTheRealCostAfterIt(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour', promptTokens: 21, completionTokens: 20)]);

        $gateway->complete($this->request());

        // 26 characters = 9 tokens at 1.00, plus the default limit of 16 000 output tokens at 2.00 USD per million.
        self::assertSame('0.03200900', $this->sent[0]['held']);
        self::assertSame(0, $this->holds());
        self::assertSame('0.00006100', $this->lastLog()->getCost());
    }

    public function testTheOutputLimitIsTheLowestOfTheRequestTheUsageAndTheModel(): void
    {
        $this->setting('aiprobe.pages', maxOutputTokens: 300);
        $this->model->setMaxOutputTokens(200)->save();
        $gateway = $this->gatewayAnswering([Wire::chat('a'), Wire::chat('b'), Wire::chat('c')]);

        $gateway->complete($this->request(maxOutputTokens: 5000));
        $this->model->setMaxOutputTokens(null)->save();
        $gateway->complete($this->request(maxOutputTokens: 5000));
        $gateway->complete($this->request(maxOutputTokens: 100));

        $sent = array_map(static fn (array $call): mixed => $call['body']['max_tokens'] ?? $call['body']['max_completion_tokens'] ?? null, $this->sent);
        self::assertSame([200, 300, 100], $sent);
        // The hold of the second call: 9 input tokens at 1.00 and 300 output tokens at 2.00.
        self::assertSame('0.00060900', $this->sent[1]['held']);
    }

    public function testAnUnknownUsageIsABugOfTheModuleAndIsNotLogged(): void
    {
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request('nobody.nothing'));
            self::fail('An undeclared usage is refused.');
        } catch (UnknownAiUsageException) {
        }

        self::assertSame(0, $this->logCount());
        self::assertSame([], $this->sent);
    }

    public function testARequestThatUsesWhatItsUsageDidNotDeclareIsAViolation(): void
    {
        $gateway = $this->gatewayAnswering([]);
        $tool = new AiToolDefinition('list', 'Lists.', ['type' => 'object']);

        foreach ([
            $this->request('aiprobe.scoring', tools: [$tool]),
            $this->request('aiprobe.scoring', outputSchema: new AiOutputSchema('page', ['type' => 'object'])),
        ] as $request) {
            try {
                $gateway->complete($request);
                self::fail('The usage did not declare it.');
            } catch (AiUsageViolationException) {
            }
        }

        self::assertSame(0, $this->logCount());
        self::assertSame([], $this->sent);
    }

    public function testAnInactiveUsageIsRefusedWithoutAnyCallNorRow(): void
    {
        $gateway = $this->gatewayAnswering([]);
        $this->setting('aiprobe.pages', enabled: false);

        $this->expectException(InactiveAiUsageException::class);

        try {
            $gateway->complete($this->request());
        } finally {
            self::assertSame(0, $this->logCount());
            self::assertSame([], $this->sent);
        }
    }

    public function testTheGlobalSwitchStopsEveryUsage(): void
    {
        $gateway = $this->gatewayAnswering([]);
        ConfigQuery::write('ai_enabled', '0');

        $this->expectException(InactiveAiUsageException::class);

        $gateway->complete($this->request());
    }

    public function testAPublicUsageWithoutBudgetIsInactive(): void
    {
        $gateway = $this->gatewayAnswering([]);

        $this->expectException(InactiveAiUsageException::class);

        $gateway->complete($this->request('aiprobe.assistant'));
    }

    public function testAModelThatCannotDoWhatTheUsageDeclaredStopsTheUsage(): void
    {
        $this->connect(tools: false);
        $gateway = $this->gatewayAnswering([]);

        $this->expectException(InactiveAiUsageException::class);

        $gateway->complete($this->request());
    }

    public function testATierChosenForTheUsageWhoseModelLacksADeclaredCapabilityStopsTheUsage(): void
    {
        $this->assignModel(AiTier::Advanced, 'advanced-no-tools', tools: false);
        $this->setting('aiprobe.pages', null, tier: AiTier::Advanced->value);
        $gateway = $this->gatewayAnswering([]);

        $this->expectException(InactiveAiUsageException::class);

        $gateway->complete($this->request());
    }

    public function testATierChosenForTheUsageDecidesWhichModelAnswers(): void
    {
        $this->assignModel(AiTier::Advanced, 'advanced-model', tools: true);
        $this->setting('aiprobe.pages', null, tier: AiTier::Advanced->value);
        $gateway = $this->gatewayAnswering([Wire::chat('Bonjour')]);

        $gateway->complete($this->request());

        self::assertSame('advanced-model', $this->sent[0]['body']['model']);
    }

    public function testAConnectionOutsideTheEuIsAcceptedAndItsJurisdictionIsLoggedWhileTheOptionIsOff(): void
    {
        $this->connect('non_eu');
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $gateway->complete($this->request());

        self::assertSame('succeeded', $this->lastLog()->getStatus());
        self::assertSame('non_eu', $this->lastLog()->getJurisdiction());
    }

    public function testWithTheEuOnlyOptionOnAConnectionOutsideTheEuIsRefusedAndTheRefusalIsLogged(): void
    {
        $this->connect('non_eu');
        ConfigQuery::write('ai_eu_only', '1');
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request());
            self::fail('The merchant allows EU providers only.');
        } catch (AiJurisdictionRefusedException $refusal) {
            self::assertSame(['probe-main', AiJurisdiction::OutsideEuropeanUnion], [$refusal->connection, $refusal->jurisdiction]);
        }

        $row = $this->lastLog();
        self::assertSame(['rejected', 'jurisdiction', '0.00000000', 'non_eu', 'probe-main'], [$row->getStatus(), $row->getError(), $row->getCost(), $row->getJurisdiction(), $row->getConnectionCode()]);
        self::assertSame([], $this->sent);
        self::assertSame(0, $this->holds());
    }

    public function testWithTheEuOnlyOptionOnAnEuConnectionRuns(): void
    {
        ConfigQuery::write('ai_eu_only', '1');
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $gateway->complete($this->request());

        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testWhatAPersonTypesIsLimitedByTheSettingThenByTheDefaultOfTheUsage(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok'), Wire::chat('ok')]);
        $this->setting('aiprobe.pages', maxInputCharacters: 10);

        try {
            $gateway->complete($this->request(prompt: 'This is eleven'));
            self::fail('Above the limit of the merchant.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame(['limits', GuardrailStage::Input, 'input_too_long'], [$blocked->verdict->guardrail, $blocked->verdict->stage, $blocked->verdict->reason]);
            self::assertNull($blocked->logId);
        }

        $row = $this->lastLog();
        self::assertSame(['blocked', 'input_too_long', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame([], $this->sent);

        // Without a limit of the merchant, the 1000 characters the usage declares.
        $this->setting('aiprobe.pages', maxInputCharacters: null);
        $gateway->complete($this->request(prompt: str_repeat('a', 1000)));

        try {
            $gateway->complete($this->request(prompt: str_repeat('a', 1001)));
            self::fail('Above the default of the usage.');
        } catch (AiGuardrailBlockedException) {
        }
    }

    public function testOnlyWhatAPersonTypedCountsForTheInputLimit(): void
    {
        $this->setting('aiprobe.pages', maxInputCharacters: 10);
        $gateway = $this->gatewayAnswering([Wire::chat('', Wire::chatToolCall()), Wire::chat('ok')]);
        $request = $this->request(prompt: 'short', tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object'])]);

        $first = $gateway->complete($request);
        $second = $gateway->complete($request->withMessages(AiMessage::fromResponse($first), AiMessage::toolResult('call_1', str_repeat('a result that is long ', 50))));

        self::assertSame('ok', $second->text);
    }

    public function testAnActorOverTheRateOfItsUsageIsRefusedAndLogged(): void
    {
        $this->setting('aiprobe.pages', requestsPerMinute: 1);
        $gateway = $this->gatewayAnswering([Wire::chat('one'), Wire::chat('two')]);

        $gateway->complete($this->request());

        try {
            $gateway->complete($this->request());
            self::fail('One request a minute.');
        } catch (AiRateLimitExceededException $tooFast) {
            self::assertSame('aiprobe.pages', $tooFast->usageId);
            self::assertGreaterThan(new \DateTimeImmutable(), $tooFast->retryAfter);
        }

        $row = $this->lastLog();
        self::assertSame(['rejected', 'rate_limit', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertCount(1, $this->sent);
        self::assertSame(0, $this->holds());

        // Another actor has its own count.
        $gateway->complete($this->request(actor: AiActor::admin(8)));
        self::assertCount(2, $this->sent);
    }

    public function testAnInputGuardrailInBlockModeRefusesBeforeTheCallAndTheRefusalIsLogged(): void
    {
        $this->installedGuardrails = [new RecordingGuardrail('probe_moderation', GuardrailVerdict::fail('probe_moderation', GuardrailStage::Input, 'violence'))];
        $this->setting('aiprobe.assistant', '5');
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', actor: AiActor::visitor('session-1')));
            self::fail('The moderation blocks.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('probe_moderation', $blocked->verdict->guardrail);
        }

        $row = $this->lastLog();
        self::assertSame(['blocked', 'violence', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame('probe_moderation', json_decode((string) $row->getGuardrailVerdicts(), true)[2]['guardrail']);
        self::assertSame([], $this->sent);
        self::assertSame(0, $this->holds());
    }

    public function testAnInputGuardrailInAnnotateModeLetsTheCallGoAndReportsItsVerdict(): void
    {
        $this->installedGuardrails = [new RecordingGuardrail('probe_moderation', GuardrailVerdict::fail('probe_moderation', GuardrailStage::Input, 'borderline'), [GuardrailStage::Input])];
        $this->setting('aiprobe.assistant', '5', guardrails: '{"probe_moderation":"annotate"}');
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', actor: AiActor::visitor('session-1')));

        self::assertCount(1, $response->annotations());
        self::assertSame(['probe_moderation', 'borderline', GuardrailMode::Annotate], [$response->annotations()[0]->guardrail, $response->annotations()[0]->reason, $response->annotations()[0]->mode]);
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testAGuardrailOfAThirdPartyNeverReceivesTheActorInClear(): void
    {
        $guardrail = new RecordingGuardrail('probe_moderation', GuardrailVerdict::pass('probe_moderation', GuardrailStage::Input), [GuardrailStage::Input]);
        $this->installedGuardrails = [$guardrail];
        $this->setting('aiprobe.assistant', '5');
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $gateway->complete($this->request('aiprobe.assistant', actor: AiActor::customer(4242)));

        self::assertCount(1, $guardrail->requests);
        $seen = $guardrail->requests[0]->actor;
        self::assertNotNull($seen);
        self::assertSame(AiActorType::Customer, $seen->type);
        self::assertSame($this->hasher->hash(AiActor::customer(4242)), $seen->identifier);
        self::assertStringNotContainsString('4242', $seen->identifier);
        self::assertSame('Hello', $guardrail->requests[0]->messages[0]->content);
    }

    public function testTheBudgetOfTheUsageRefusesACallThatMayCostMoreThanWhatIsLeft(): void
    {
        $this->setting('aiprobe.pages', '1');
        $this->spend('aiprobe.pages', '0.98000000');
        $gateway = $this->gatewayAnswering([]);

        try {
            // The worst case of the call is 0.032009: more than the 0.02 left.
            $gateway->complete($this->request());
            self::fail('The budget cannot take it.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertFalse($refusal->globalCap);
        }

        $row = $this->lastLog();
        self::assertSame(['rejected', 'budget', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame('budget', array_values(array_filter(json_decode((string) $row->getGuardrailVerdicts(), true), static fn (array $verdict): bool => !$verdict['passed']))[0]['guardrail']);
        self::assertSame([], $this->sent);
        self::assertSame(0, $this->holds());
    }

    public function testTheGlobalCapIsASumOverTheUsages(): void
    {
        ConfigQuery::write('ai_monthly_cap', '2');
        $this->spend('aiprobe.scoring', '1.99000000');
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request());
            self::fail('The cap cannot take it.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertTrue($refusal->globalCap);
        }

        self::assertSame(['rejected', 'budget_global'], [$this->lastLog()->getStatus(), $this->lastLog()->getError()]);
    }

    public function testTheCostTheProviderAnnouncesIsTheOneThatIsLogged(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok', promptTokens: 21, completionTokens: 20, usageExtra: ['cost' => 0.0123])]);

        $response = $gateway->complete($this->request());

        self::assertSame('0.01230000', $response->cost);
        self::assertSame('0.01230000', $this->lastLog()->getCost());
    }

    public function testAnUnavailableProviderIsRetriedOnceAndTheSecondAnswerIsTheResult(): void
    {
        $gateway = $this->gatewayAnswering([$this->overloaded(), Wire::chat('back')]);

        $response = $gateway->complete($this->request());

        self::assertSame('back', $response->text);
        self::assertCount(2, $this->sent);
        self::assertSame('succeeded', $this->lastLog()->getStatus());
        self::assertSame(1, $this->logCount());
        self::assertSame(0, $this->holds());
    }

    public function testAProviderStillDownAfterTheRetryFailsWithTheRowItLoggedAndNoCost(): void
    {
        $gateway = $this->gatewayAnswering([$this->overloaded(), $this->overloaded(), Wire::chat('never read')]);

        try {
            $gateway->complete($this->request());
            self::fail('The provider is down.');
        } catch (AiProviderUnavailableException $down) {
            self::assertTrue($down->retryable);
            self::assertSame($this->lastLog()->getId(), $down->logId);
            self::assertSame('probe-main', $down->connection);
        }

        self::assertCount(2, $this->sent);
        self::assertSame(['failed', 'provider_unavailable_retryable', '0.00000000'], [$this->lastLog()->getStatus(), $this->lastLog()->getError(), $this->lastLog()->getCost()]);
        self::assertSame(0, $this->holds());
    }

    public function testARefusedKeyIsNotRetried(): void
    {
        $gateway = $this->gatewayAnswering([Wire::json(['error' => ['message' => 'bad key', 'type' => 'invalid_request_error']], 401), Wire::chat('never read')]);

        try {
            $gateway->complete($this->request());
            self::fail('The key is refused.');
        } catch (AiProviderUnavailableException $refused) {
            self::assertFalse($refused->retryable);
        }

        self::assertCount(1, $this->sent);
        self::assertSame('provider_unavailable', $this->lastLog()->getError());
    }

    public function testAnUnexpectedErrorStillGivesTheReservationBack(): void
    {
        // Nothing is queued: the transport of the test throws a LogicException, a bug, not a provider failure.
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request());
            self::fail('The bug must come out.');
        } catch (\Throwable $bug) {
            self::assertNotInstanceOf(AiProviderUnavailableException::class, $bug);
        }

        self::assertSame(1, \count($this->sent));
        self::assertSame('0.03200900', $this->sent[0]['held']);
        self::assertSame(0, $this->holds());
    }

    public function testThePromptIsStoredOnlyWhenTheUsageAsksForItAndExpires(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok'), Wire::chat('ok'), Wire::chat('ok')]);

        $gateway->complete($this->request());
        self::assertNull($this->lastLog()->getPrompt());

        $this->setting('aiprobe.pages', storePrompt: true, promptRetentionDays: 7);
        $gateway->complete($this->request(prompt: 'Describe the chair'));

        $row = $this->lastLog();
        $prompt = json_decode((string) $row->getPrompt(), true);
        self::assertSame('Describe the chair', $prompt['messages'][0]['content']);
        self::assertSame('You write for a shop.', $prompt['system']);
        self::assertStringNotContainsString('sk-probe', (string) $row->getPrompt());
        self::assertEqualsWithDelta(time() + 7 * 86400, $row->getPromptExpiresAt()?->getTimestamp(), 5);

        $this->setting('aiprobe.pages', storePrompt: true, promptRetentionDays: null);
        $gateway->complete($this->request());
        self::assertEqualsWithDelta(time() + 30 * 86400, $this->lastLog()->getPromptExpiresAt()?->getTimestamp(), 5);
    }

    public function testARefusalNeverStoresAPrompt(): void
    {
        $this->setting('aiprobe.pages', storePrompt: true, requestsPerMinute: 1);
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);
        $gateway->complete($this->request());

        try {
            $gateway->complete($this->request());
        } catch (AiRateLimitExceededException) {
        }

        self::assertNull($this->lastLog()->getPrompt());
    }

    public function testAnOutputGuardrailInBlockModeRefusesTheAnswerThatWasPaidAndPointsAtItsRow(): void
    {
        $this->installedGuardrails = [new RecordingGuardrail('probe_topic', GuardrailVerdict::fail('probe_topic', GuardrailStage::Output, 'off_topic'), [GuardrailStage::Output])];
        $this->setting('aiprobe.pages', guardrails: '{"probe_topic":"block"}');
        $gateway = $this->gatewayAnswering([Wire::chat('The weather', promptTokens: 21, completionTokens: 20)]);

        try {
            $gateway->complete($this->request());
            self::fail('The topic guardrail blocks the answer.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame(GuardrailStage::Output, $blocked->verdict->stage);
            self::assertSame($this->lastLog()->getId(), $blocked->logId);
        }

        $row = $this->lastLog();
        self::assertSame(['blocked', 'off_topic', '0.00006100'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame(0, $this->holds());
    }

    public function testTheOutputGuardrailSeesTheAnswerAndAnnotatesInAnnotateMode(): void
    {
        $guardrail = new RecordingGuardrail('probe_topic', GuardrailVerdict::fail('probe_topic', GuardrailStage::Output, 'off_topic'), [GuardrailStage::Output]);
        $this->installedGuardrails = [$guardrail];
        $this->setting('aiprobe.pages', guardrails: '{"probe_topic":"annotate"}');
        $gateway = $this->gatewayAnswering([Wire::chat('The weather')]);

        $response = $gateway->complete($this->request());

        self::assertSame(['The weather'], $guardrail->outputs);
        self::assertSame(['off_topic'], array_map(static fn (GuardrailVerdict $verdict): ?string => $verdict->reason, $response->annotations()));
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testAGenerativeUsageMarksItsAnswerUnlessTheMerchantTurnedTheMarkOff(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('a'), Wire::chat('b'), Wire::chat('c')]);

        $marked = $gateway->complete($this->request());
        self::assertNotNull($marked->provenance);
        self::assertSame(['ai_generated' => true, 'usage' => 'aiprobe.pages', 'model' => 'probe-model-2026', 'log_id' => $marked->logId], array_diff_key($marked->provenance->toArray(), ['generated_at' => 0]));

        $this->setting('aiprobe.pages', provenanceMark: false);
        self::assertNull($gateway->complete($this->request())->provenance);

        // An interactive usage owes a notice, not a mark.
        $this->setting('aiprobe.assistant', '5', guardrails: '{}');
        self::assertNull($gateway->complete($this->request('aiprobe.assistant', actor: AiActor::visitor('s')))->provenance);
    }

    public function testTheToolCallsOfTheModelComeBackForTheModuleToRun(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('', Wire::chatToolCall())]);

        $response = $gateway->complete($this->request(tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object'])]));

        self::assertTrue($response->hasToolCalls());
        self::assertSame(AiFinishReason::ToolCalls, $response->finishReason);
        self::assertSame('tool_calls', $this->lastLog()->getFinishReason());
    }

    public function testWithoutAnActorTheResolverSaysWhoIsAsking(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $gateway->complete(new AiRequest('aiprobe.pages', [AiMessage::user('Hello')]));

        self::assertSame(1, $this->resolver->calls);
        self::assertSame(['visitor', $this->hasher->hash(AiActor::visitor('resolved-visitor'))], [$this->lastLog()->getActorType(), $this->lastLog()->getActorHash()]);
        self::assertSame(AiActorType::Visitor->value, $this->lastLog()->getActorType());
    }

    public function testTheResolverIsToldTheAudienceOfTheUsage(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok'), Wire::chat('ok')]);

        $gateway->complete(new AiRequest('aiprobe.pages', [AiMessage::user('Hello')]));
        $gateway->complete(new AiRequest('aiprobe.scoring', [AiMessage::user('Hello')]));

        self::assertSame([AiAudience::Admin, AiAudience::System], $this->resolver->audiences);
    }

    public function testAnActorTheRequestNamesIsNotLookedUp(): void
    {
        $gateway = $this->gatewayAnswering([Wire::chat('ok')]);

        $gateway->complete($this->request());

        self::assertSame(0, $this->resolver->calls);
    }

    private function overloaded(): MockResponse
    {
        return Wire::json(['error' => ['message' => 'Overloaded', 'type' => 'api_error']], 503);
    }

    private function assignModel(AiTier $tier, string $name, bool $tools): void
    {
        $model = (new AiModel())
            ->setProvider('openai_compatible')->setName($name)->setConnectionId($this->connection->getId())
            ->setInputPrice('1.000000')->setOutputPrice('2.000000')->setSupportsStructuredOutput(true)->setSupportsTools($tools)->setSupportsStreaming(true)
            ->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();
        (new AiTierAssignment())->setTier($tier->value)->setConnectionId($this->connection->getId())->setModelId($model->getId())->save();
    }
}

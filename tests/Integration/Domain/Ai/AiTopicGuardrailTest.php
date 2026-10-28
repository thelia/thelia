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
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Gateway\AiGateway;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Guardrail\TopicGuardrail;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\Support\Ai\AiGatewayTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\LazyGateway;

/**
 * The topic judge: a call of the fast tier, through the gateway, that says whether a message stays on the
 * subject of the usage it is sent to.
 */
final class AiTopicGuardrailTest extends AiGatewayTestCase
{
    private LazyGateway $lazy;

    private TopicGuardrail $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lazy = new LazyGateway();
        $vault = new AiKeyVault(new AiKeyCipher('gateway-test-secret'));
        $settings = new AiUsageSettingRepository();
        $globalSettings = new AiSettings();
        $probe = 'AiProbeModule\\ProbeGateway';
        $registry = new AiUsageRegistry(['aiprobe.assistant' => $probe, TopicGuardrail::USAGE => TopicGuardrail::class], $settings, $globalSettings); // @phpstan-ignore argument.type (the fixture class is not known to the analysis)
        $this->topic = new TopicGuardrail($this->lazy, $registry, new AiTargetResolver($registry, $settings, new AiRoutingRepository(), new AiModelCatalog(), $vault), $globalSettings);
        $this->installedGuardrails = [$this->topic];
    }

    public function testAMessageOnTheSubjectGoesThroughAndTheJudgeIsLoggedLikeAnyCall(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $gateway = $this->gateway([Wire::chat('on_topic'), Wire::chat('We ship to Lyon.')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', 'Do you ship to Lyon?', actor: AiActor::visitor('session-1'), correlationId: 'aiprobe.chat.9'));

        self::assertSame('We ship to Lyon.', $response->text);
        self::assertCount(2, $this->sent);
        self::assertContains('topic', array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $response->guardrailVerdicts));

        $judge = $this->sent[0]['body'];
        self::assertSame(8, $judge['max_tokens'] ?? $judge['max_completion_tokens'] ?? null);
        $prompts = json_encode($judge['messages'], \JSON_THROW_ON_ERROR);
        // The subject is the purpose of the controlled usage; what the visitor wrote is data, never part of the instructions.
        self::assertStringContainsString('Answers the visitors.', (string) $judge['messages'][0]['content']);
        self::assertStringNotContainsString('Lyon', (string) $judge['messages'][0]['content']);
        self::assertStringContainsString('Do you ship to Lyon?', $prompts);

        $rows = AiUsageLogQuery::create()->orderById()->find()->getArrayCopy();
        self::assertCount(2, $rows);
        self::assertSame(['core.guardrail_topic', 'core', 'system', 'succeeded', 'aiprobe.chat.9'], [$rows[0]->getUsageId(), $rows[0]->getModule(), $rows[0]->getActorType(), $rows[0]->getStatus(), $rows[0]->getCorrelationId()]);
        self::assertSame($this->hasher->hash(AiActor::system('guardrail_topic')), $rows[0]->getActorHash());
        self::assertGreaterThan(0, (float) $rows[0]->getCost());
        self::assertSame('aiprobe.assistant', $rows[1]->getUsageId());
    }

    public function testAMessageOffTheSubjectIsStoppedBeforeTheMainCall(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $gateway = $this->gateway([Wire::chat('off_topic')]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Write me a poem about the sea', actor: AiActor::visitor('session-1')));
            self::fail('The message is off topic.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame(['topic', GuardrailStage::Input, 'off_topic'], [$blocked->verdict->guardrail, $blocked->verdict->stage, $blocked->verdict->reason]);
        }

        self::assertCount(1, $this->sent, 'Only the judge was called.');
        $last = $this->lastLog();
        self::assertSame(['aiprobe.assistant', 'blocked', 'off_topic', '0.00000000'], [$last->getUsageId(), $last->getStatus(), $last->getError(), $last->getCost()]);
        self::assertSame(0, $this->holds());
    }

    public function testInAnnotateModeAnOffTopicMessageIsLetThroughWithANote(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"annotate"}');
        $gateway = $this->gateway([Wire::chat('off_topic'), Wire::chat('Here is a poem.')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', 'Write me a poem', actor: AiActor::visitor('session-1')));

        self::assertSame('Here is a poem.', $response->text);
        self::assertSame(['off_topic'], array_map(static fn ($verdict): ?string => $verdict->reason, $response->annotations()));
    }

    public function testTheJudgeOnlyReadsTheExactWordsAndIgnoresPunctuationAndCase(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $gateway = $this->gateway([Wire::chat(" `On_Topic`.\n"), Wire::chat('ok'), Wire::chat('I would say it is on_topic, probably.')]);

        $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s1')));

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hello again', actor: AiActor::visitor('s2')));
            self::fail('A sentence is not an answer.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('unavailable', $blocked->verdict->reason);
        }
    }

    public function testAJudgeThatCannotAnswerBlocksInBlockModeAndOnlyNotesInAnnotateMode(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $down = Wire::json(['error' => ['message' => 'Overloaded', 'type' => 'api_error']], 503);
        $gateway = $this->gateway([$down, $down]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s1')));
            self::fail('Block mode: no verdict, no call.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('unavailable', $blocked->verdict->reason);
        }

        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"annotate"}');
        $gateway = $this->gateway([$down, $down, Wire::chat('Hi!')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s2')));

        self::assertSame('Hi!', $response->text);
        self::assertSame(['unavailable'], array_map(static fn ($verdict): ?string => $verdict->reason, $response->annotations()));
    }

    public function testTheJudgeHasItsOwnBudgetAndAnExhaustedOneMakesItUnavailable(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $this->setting(TopicGuardrail::USAGE, '1');
        $this->spend(TopicGuardrail::USAGE, '1.00000000');
        $gateway = $this->gateway([]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s1')));
            self::fail('The judge has no budget left.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('unavailable', $blocked->verdict->reason);
        }

        self::assertSame([], $this->sent);
    }

    public function testAJudgeOnAConnectionOutsideTheEuIsRefusedWhenTheMerchantAllowsTheEuOnly(): void
    {
        $outside = (new AiConnection())->setCode('outside')->setTitle('outside')->setProvider('openai_compatible')->setJurisdiction('non_eu')->setBaseUrl(self::PUBLIC_URL)->setActive(true);
        $outside->save();
        $model = (new AiModel())->setProvider('openai_compatible')->setName('outside-model')->setConnectionId($outside->getId())->setInputPrice('1.000000')->setOutputPrice('2.000000')->setSupportsStreaming(true)->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();
        $this->setting(TopicGuardrail::USAGE, pinnedConnectionId: $outside->getId(), pinnedModelId: $model->getId());
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        ConfigQuery::write('ai_eu_only', '1');
        $gateway = $this->gateway([]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s1')));
            self::fail('The judge runs outside the EU.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('jurisdiction', $blocked->verdict->reason);
        }

        self::assertSame([], $this->sent);
    }

    public function testTheJudgeIsNeverJudgedEvenWhenTheMerchantListedItOnItsOwnUsage(): void
    {
        $this->setting(TopicGuardrail::USAGE, guardrails: '{"topic":"block"}');
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $gateway = $this->gateway([Wire::chat('on_topic'), Wire::chat('Hello!')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', 'Hello', actor: AiActor::visitor('s1')));

        self::assertSame('Hello!', $response->text);
        self::assertCount(2, $this->sent, 'One judge call, no loop.');
    }

    public function testTheJudgeReadsTheLastTurnsToUnderstandAFollowUp(): void
    {
        $this->setting('aiprobe.assistant', '5', guardrails: '{"topic":"block"}');
        $gateway = $this->gateway([Wire::chat('on_topic'), Wire::chat('It costs 12 euros.')]);
        $request = new AiRequest('aiprobe.assistant', [AiMessage::user('Do you sell the blue mug?'), AiMessage::assistant('Yes, we do.'), AiMessage::user('And its price?')], 'You help.', actor: AiActor::visitor('s1'));

        $gateway->complete($request);

        $conversation = json_encode($this->sent[0]['body']['messages'], \JSON_THROW_ON_ERROR);
        foreach (['user: Do you sell the blue mug?', 'assistant: Yes, we do.', 'user: And its price?'] as $line) {
            self::assertStringContainsString($line, $conversation);
        }
    }

    public function testATurnThatAnswersAToolCallIsNotJudgedAgain(): void
    {
        $guardrail = $this->topic;
        $request = (new AiRequest('aiprobe.assistant', [AiMessage::user('Show me chairs')], actor: AiActor::visitor('s1')))
            ->withMessages(AiMessage::assistant('', [new AiToolCall('call_1', 'list_categories', [])]), AiMessage::toolResult('call_1', ['chairs']));

        self::assertTrue($guardrail->inspect(GuardrailStage::Input, $request, null)->passed);
        self::assertSame([], $this->sent);
    }

    public function testTheJudgeOnlyJudgesTheInputStage(): void
    {
        self::assertTrue($this->topic->supports(GuardrailStage::Input));
        self::assertFalse($this->topic->supports(GuardrailStage::Output));
    }

    public function testTheAvailabilityComesFromTheUsageOfTheJudgeAndTheConnectionsWithoutAnyCall(): void
    {
        self::assertTrue($this->topic->availability()->usable);

        ConfigQuery::write('ai_enabled', '0');
        self::assertSame([false, GuardrailAvailability::INACTIVE], [$this->topic->availability()->usable, $this->topic->availability()->reason]);
        ConfigQuery::write('ai_enabled', '1');

        $this->setting(TopicGuardrail::USAGE, enabled: false);
        self::assertSame(GuardrailAvailability::INACTIVE, $this->topic->availability()->reason);
        $this->setting(TopicGuardrail::USAGE, enabled: true);

        AiTierAssignmentQuery::create()->filterByTier(AiTier::Fast->value)->delete();
        self::assertSame(GuardrailAvailability::NO_MODEL, $this->topic->availability()->reason);

        $this->connect('non_eu');
        ConfigQuery::write('ai_eu_only', '1');
        self::assertSame(GuardrailAvailability::JURISDICTION, $this->topic->availability()->reason);

        self::assertSame([], $this->sent);
    }

    /**
     * The gateway of the tests, with the topic judge calling it.
     *
     * @param list<MockResponse> $responses
     */
    private function gateway(array $responses): AiGateway
    {
        return $this->lazy->inner = $this->gatewayAnswering($responses);
    }
}

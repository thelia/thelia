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
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiCapability;
use Thelia\Domain\Ai\AiCostRecorderInterface;
use Thelia\Domain\Ai\AiExternalCost;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\AiProvenance;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Compliance\AiActObligation;
use Thelia\Domain\Ai\Compliance\AiComplianceStatus;
use Thelia\Domain\Ai\Compliance\ComplianceState;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Stream\StreamCompleted;
use Thelia\Domain\Ai\Stream\TextDelta;
use Thelia\Domain\Ai\Stream\ToolCallReceived;
use Thelia\Domain\Ai\UntrustedContent;
use Thelia\Domain\Ai\UntrustedSource;

/**
 * Builds the objects of the example of the frozen contract (.claude/plans/thelia-ai/API.md), so that the
 * example compiles against the real classes, and checks that the value objects refuse what they must.
 */
final class ApiContractExampleTest extends TestCase
{
    private const string APPLICATION_SECRET = 'ThisTokenIsNotSoSecretChangeIt';

    public function testTheThreeModulesDeclareTheirUsages(): void
    {
        $page = self::usageOf(new #[AsAiUsage(id: 'theliacms.page_generation', purpose: 'Writes a CMS page from the brief of an administrator.', audience: AiAudience::Admin, tier: AiTier::Advanced, capabilities: [AiCapability::StructuredOutput, AiCapability::Tools], transparency: AiTransparency::Generative, )] class {});

        $chat = self::usageOf(new #[AsAiUsage(id: 'commerceagents.shopping_assistant', purpose: 'Answers the questions of visitors about the catalogue.', audience: AiAudience::Public, tier: AiTier::Fast, capabilities: [AiCapability::Tools, AiCapability::Streaming], transparency: AiTransparency::Interactive, guardrails: ['mistral_moderation' => GuardrailMode::Block], )] class {});

        $judgement = self::usageOf(new #[AsAiUsage(id: 'pricewatch.offer_judgement', purpose: 'Judges whether a competitor offer sells the same product.', audience: AiAudience::System, tier: AiTier::Fast, )] class {});

        self::assertSame('theliacms', $page->module());
        self::assertTrue($page->requires(AiCapability::Tools));
        self::assertFalse($page->requires(AiCapability::Streaming));

        self::assertSame('commerceagents', $chat->module());
        self::assertSame(GuardrailMode::Block, $chat->guardrails['mistral_moderation']);

        self::assertSame('pricewatch', $judgement->module());
        self::assertSame(AiTransparency::None, $judgement->transparency);

        // AI Act: the tools are on by default, the merchant may turn them off, the core then says so.
        self::assertSame([ComplianceState::Met], self::states(AiComplianceStatus::evaluate($chat, noticeShown: true, provenanceMarked: true)));
        $notice = AiComplianceStatus::evaluate($chat, noticeShown: false, provenanceMarked: true);
        self::assertSame([ComplianceState::NotMet], self::states($notice));
        self::assertSame(AiActObligation::Interaction, $notice[0]->obligation);
        self::assertStringContainsString('article 50(1)', $notice[0]->message);
        self::assertSame(['%usage%' => 'commerceagents.shopping_assistant'], $notice[0]->parameters);
        self::assertSame([ComplianceState::NotMet], self::states(AiComplianceStatus::evaluate($page, noticeShown: true, provenanceMarked: false)));
        self::assertSame(AiActObligation::GeneratedContent, AiComplianceStatus::evaluate($page, true, true)[0]->obligation);
        self::assertSame([], AiComplianceStatus::evaluate($judgement, noticeShown: false, provenanceMarked: false), 'A system job falls under no transparency duty.');

        $publicWithoutDuty = new AsAiUsage('commerceagents.smart_search', 'Ranks the products of a search.', AiAudience::Public, AiTier::Fast);
        self::assertSame([ComplianceState::ToReview], self::states(AiComplianceStatus::evaluate($publicWithoutDuty, true, true)));
    }

    /**
     * @param list<AiComplianceStatus> $statuses
     *
     * @return list<ComplianceState>
     */
    private static function states(array $statuses): array
    {
        return array_map(static fn (AiComplianceStatus $status): ComplianceState => $status->state, $statuses);
    }

    public function testThePageGenerationRunsOneTurnOfItsToolLoop(): void
    {
        $listCategories = new AiToolCall('toolu_01', 'list_categories', ['limit' => 20]);
        $gateway = new ScriptedGateway([
            self::response('', null, [$listCategories], new AiTokenUsage(4812, 1206), '0.05421000', 'claude-opus-5-5', 'anthropic-main', AiFinishReason::ToolCalls, 9001, 'theliacms.page_generation'),
            self::response('', ['title' => 'Our coffee range', 'blocks' => []], [], new AiTokenUsage(6140, 3880), '0.12770000', 'claude-opus-5-5', 'anthropic-main', AiFinishReason::Completed, 9002, 'theliacms.page_generation'),
        ]);

        $request = new AiRequest(
            usageId: 'theliacms.page_generation',
            messages: [AiMessage::user('A landing page for our coffee range, friendly tone, 3 sections.')],
            system: 'You write CMS pages for a Thelia shop. Answer with the page as JSON.',
            untrustedContents: [new UntrustedContent(UntrustedSource::WebPage, 'Arabica beans roasted in Lyon since 1952...', 'https://example.com/our-coffee')],
            outputSchema: new AiOutputSchema('cms_page', ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'blocks' => ['type' => 'array']], 'required' => ['title', 'blocks'], 'additionalProperties' => false]),
            tools: [new AiToolDefinition('list_categories', 'Lists the categories of the catalogue with their URL.', ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]])],
            maxOutputTokens: 16000,
            providerOptions: ['anthropic' => ['effort' => 'high']],
            correlationId: 'theliacms.page.42',
        );

        $page = null;

        for ($turn = 1; $turn <= 12; ++$turn) {
            $response = $gateway->complete($request);

            if (!$response->hasToolCalls()) {
                $page = ['content' => $response->data, 'provenance' => $response->provenance?->toArray()];

                break;
            }

            $results = array_map(
                static fn (AiToolCall $call): AiMessage => AiMessage::toolResult($call->id, [['id' => 3, 'title' => 'Coffee', 'url' => '/coffee']]),
                $response->toolCalls,
            );
            $request = $request->withMessages(AiMessage::fromResponse($response), ...$results);
        }

        self::assertCount(2, $gateway->requests);
        $secondTurn = $gateway->requests[1];
        self::assertSame([AiMessageRole::User, AiMessageRole::Assistant, AiMessageRole::Tool], array_map(static fn (AiMessage $message): AiMessageRole => $message->role, $secondTurn->messages));
        self::assertSame('toolu_01', $secondTurn->messages[2]->toolCallId);
        self::assertSame('[{"id":3,"title":"Coffee","url":"/coffee"}]', $secondTurn->messages[2]->content);
        self::assertSame('theliacms.page.42', $secondTurn->correlationId);
        self::assertSame(['effort' => 'high'], $secondTurn->optionsFor(AiProvider::Anthropic));
        self::assertSame([], $secondTurn->optionsFor(AiProvider::Mistral), 'An option of another provider is never sent.');
        self::assertNotNull($page);
        self::assertSame(['title' => 'Our coffee range', 'blocks' => []], $page['content']);
        self::assertSame(
            ['ai_generated' => true, 'usage' => 'theliacms.page_generation', 'model' => 'claude-opus-5-5', 'generated_at' => '2026-10-28T10:15:00+00:00', 'log_id' => 9002],
            $page['provenance'],
        );
    }

    public function testTheShoppingAssistantStreamsItsAnswer(): void
    {
        $final = self::response('We have three decaf coffees.', null, [], new AiTokenUsage(1530, 212), '0.00006968', 'ministral-3b-latest', 'mistral-main', AiFinishReason::Completed, 9003, null);
        $gateway = new ScriptedGateway([], [new TextDelta('We have three '), new TextDelta('decaf coffees.'), new StreamCompleted($final)]);

        $text = '';
        $logId = null;
        $toolCalls = [];

        foreach ($gateway->stream(new AiRequest(
            usageId: 'commerceagents.shopping_assistant',
            messages: [AiMessage::user('Do you sell decaf coffee?')],
            system: 'You help the visitors of a coffee shop. Never promise a delivery date.',
            tools: [new AiToolDefinition('search_products', 'Searches the catalogue.', ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']])],
            maxOutputTokens: 1024,
            correlationId: 'commerceagents.conversation.7f3a',
        )) as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->text;
            } elseif ($event instanceof ToolCallReceived) {
                $toolCalls[] = $event->toolCall;
            } elseif ($event instanceof StreamCompleted) {
                $logId = $event->response->logId;
            }
        }

        self::assertSame('We have three decaf coffees.', $text);
        self::assertSame([], $toolCalls);
        self::assertSame(9003, $logId);
        self::assertSame(1742, $final->tokenUsage->total());
    }

    public function testAUsageCanDeclareItsInputSizeAsTheDefaultOfTheMerchantSetting(): void
    {
        $usage = new AsAiUsage('commerceagents.shopping_assistant', 'p', AiAudience::Public, AiTier::Fast, maxInputCharacters: 2000);

        self::assertSame(2000, $usage->maxInputCharacters);
        self::assertNull((new AsAiUsage('theliacms.page', 'p', AiAudience::Admin, AiTier::Fast))->maxInputCharacters);
    }

    public function testTheContractHasNoToolRunByTheProvider(): void
    {
        foreach ([AsAiUsage::class, AiRequest::class] as $class) {
            $names = array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), (new \ReflectionMethod($class, '__construct'))->getParameters());

            self::assertNotContains('serverTools', $names, $class);
        }
    }

    public function testAuthorizeTakesTheCeilingOfTheCallAsAnOptionalDecimal(): void
    {
        $parameters = (new \ReflectionMethod(AiCostRecorderInterface::class, 'authorize'))->getParameters();

        self::assertSame('maximumCost', $parameters[3]->getName());
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertSame('0', $parameters[3]->getDefaultValue());
    }

    public function testPriceWatchRecordsTheCostOfAJevJudgement(): void
    {
        $recorder = new class implements AiCostRecorderInterface {
            /** The optional setting "EU only", off by default. */
            public bool $europeanUnionOnly = false;

            /** @var list<AiExternalCost> */
            public array $recorded = [];

            public function authorize(string $usageId, string $provider, AiJurisdiction $jurisdiction, string $maximumCost = '0'): void
            {
                if ($this->europeanUnionOnly && AiJurisdiction::EuropeanUnion !== $jurisdiction) {
                    throw new AiJurisdictionRefusedException($provider, $jurisdiction);
                }
            }

            public function record(AiExternalCost $cost): int
            {
                $this->recorded[] = $cost;

                return 9004;
            }
        };

        // By default a service outside the EU is allowed (the back office shows a warning).
        $recorder->authorize('pricewatch.offer_judgement', 'typesafe', AiJurisdiction::OutsideEuropeanUnion);
        // A module that knows the ceiling of the call passes it, so the budget can hold it back.
        $recorder->authorize('pricewatch.offer_judgement', 'typesafe', AiJurisdiction::OutsideEuropeanUnion, maximumCost: '0.00020000');

        $recorder->europeanUnionOnly = true;

        try {
            $recorder->authorize('pricewatch.offer_judgement', 'typesafe', AiJurisdiction::OutsideEuropeanUnion);
            self::fail('Once the merchant turns "EU only" on, Jev is refused.');
        } catch (AiJurisdictionRefusedException $refused) {
            self::assertSame('typesafe', $refused->connection);
        }

        $recorder->europeanUnionOnly = false;

        $logId = $recorder->record(new AiExternalCost(
            usageId: 'pricewatch.offer_judgement',
            provider: 'typesafe',
            operation: 'jev-latest',
            cost: '0.00009870',
            tokenUsage: new AiTokenUsage(2350, 0),
            latencyMilliseconds: 840,
            correlationId: 'pricewatch.collect.118',
            actor: AiActor::system('pricewatch:collect'),
        ));

        self::assertSame(9004, $logId);
        self::assertSame('USD', $recorder->recorded[0]->currency);
        self::assertSame(AiActorType::System, $recorder->recorded[0]->actor?->type);
    }

    /**
     * The values of the log rows the contract shows: cost = tokens x catalogue price per million, and the
     * actor hashed with the application secret.
     */
    public function testTheLogRowsOfTheExampleAreComputedTheWayTheContractSays(): void
    {
        self::assertSame('0.05421000', self::cost([4812 => '5.000000', 1206 => '25.000000']));
        self::assertSame('0.12770000', self::cost([6140 => '5.000000', 3880 => '25.000000']));
        self::assertSame('0.00006968', self::cost([1530 => '0.040000', 212 => '0.040000']));
        self::assertSame('0.00009870', self::cost([2350 => '0.042000']));

        self::assertSame('bdb64122bc45f5fdbdc658243c3fa164e05c5eb7c06ab510f59e31a860d6ca06', hash_hmac('sha256', 'admin:1', self::APPLICATION_SECRET));
        self::assertSame('65bdd52e0974b8db57afd8b7204e5e92668f7122136bfab0d139b6368078db04', hash_hmac('sha256', 'visitor:k3v9q2c8m1x7r5t0b4n6', self::APPLICATION_SECRET));
        self::assertSame('14290a481a9b6d5d56f08760e17617f752e093342480f2671369b4faa560c2f8', hash_hmac('sha256', 'system:pricewatch:collect', self::APPLICATION_SECRET));

        self::assertSame(
            ['guardrail' => 'mistral_moderation', 'stage' => 'input', 'passed' => true, 'mode' => 'block', 'reason' => null],
            GuardrailVerdict::pass('mistral_moderation', GuardrailStage::Input)->toArray(),
        );
    }

    public function testTheJurisdictionOfAConnectionFollowsItsProviderAndItsBaseUrl(): void
    {
        self::assertSame(AiJurisdiction::EuropeanUnion, AiProvider::Mistral->defaultJurisdiction());
        self::assertSame(AiJurisdiction::OutsideEuropeanUnion, AiProvider::Anthropic->defaultJurisdiction());
        self::assertSame(AiJurisdiction::OutsideEuropeanUnion, AiProvider::OpenAi->defaultJurisdiction());
        self::assertSame(AiJurisdiction::OutsideEuropeanUnion, AiProvider::OpenAi->defaultJurisdiction('https://api.openai.com/v1'));
        self::assertSame(AiJurisdiction::EuropeanUnion, AiProvider::OpenAi->defaultJurisdiction('https://eu.api.openai.com/v1'));
        self::assertNull(AiProvider::Ollama->defaultJurisdiction('http://192.168.1.20:11434'), 'Self-hosted: the merchant declares it.');
        self::assertNull(AiProvider::OpenAiCompatible->defaultJurisdiction('https://openrouter.ai/api/v1'));
    }

    public function testEveryExceptionAModuleCatchesIsAnAiException(): void
    {
        $blocked = new AiGuardrailBlockedException(GuardrailVerdict::fail('mistral_moderation', GuardrailStage::Input, 'category: hate'));
        $unavailable = new AiProviderUnavailableException('mistral-main', true);

        self::assertInstanceOf(AiExceptionInterface::class, $blocked);
        self::assertTrue($blocked->verdict->blocks());
        self::assertFalse(GuardrailVerdict::fail('mistral_moderation', GuardrailStage::Output, 'category: hate')->withMode(GuardrailMode::Annotate)->blocks());
        self::assertTrue($unavailable->retryable);
        self::assertInstanceOf(AiExceptionInterface::class, new InvalidAiArgumentException('x'));
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function invalidInputs(): iterable
    {
        $user = AiMessage::user('Hello');

        yield 'usage id with the module code in camel case' => [static fn () => new AsAiUsage('TheliaCMS.page', 'p', AiAudience::Admin, AiTier::Fast)];
        yield 'usage id without purpose' => [static fn () => new AsAiUsage('theliacms', 'p', AiAudience::Admin, AiTier::Fast)];
        yield 'usage without purpose' => [static fn () => new AsAiUsage('theliacms.page', ' ', AiAudience::Admin, AiTier::Fast)];
        yield 'usage repeating a capability' => [static fn () => new AsAiUsage('theliacms.page', 'p', AiAudience::Admin, AiTier::Fast, [AiCapability::Tools, AiCapability::Tools])];
        yield 'usage listing a core guardrail' => [static fn () => new AsAiUsage('theliacms.page', 'p', AiAudience::Admin, AiTier::Fast, guardrails: [CoreGuardrail::Budget->value => GuardrailMode::Block])];
        yield 'usage with no room for input' => [static fn () => new AsAiUsage('theliacms.page', 'p', AiAudience::Admin, AiTier::Fast, maxInputCharacters: 0)];
        yield 'request without message' => [static fn () => new AiRequest('theliacms.page', [])];
        yield 'request ending on the model' => [static fn () => new AiRequest('theliacms.page', [$user, AiMessage::assistant('Hi')])];
        yield 'tool result answering no call' => [static fn () => new AiRequest('theliacms.page', [$user, AiMessage::toolResult('toolu_99', [])])];
        // @phpstan-ignore argument.type (the wrong type is the point)
        yield 'request holding something else than messages' => [static fn () => new AiRequest('theliacms.page', ['Hello'])];
        yield 'options of an unknown provider' => [static fn () => new AiRequest('theliacms.page', [$user], providerOptions: ['claude' => ['effort' => 'high']])];
        yield 'output limit of zero' => [static fn () => new AiRequest('theliacms.page', [$user], maxOutputTokens: 0)];
        yield 'correlation identifier with a space' => [static fn () => new AiRequest('theliacms.page', [$user], correlationId: 'page 42')];
        yield 'two tools of the same name' => [static fn () => new AiRequest('theliacms.page', [$user], tools: [new AiToolDefinition('a', 'd', ['type' => 'object']), new AiToolDefinition('a', 'd', ['type' => 'object'])])];
        yield 'tool name with a space' => [static fn () => new AiToolDefinition('list categories', 'd', ['type' => 'object'])];
        yield 'tool schema that is not an object' => [static fn () => new AiToolDefinition('list', 'd', ['type' => 'array'])];
        yield 'output schema that is not an object' => [static fn () => new AiOutputSchema('page', ['type' => 'string'])];
        yield 'blank untrusted content' => [static fn () => new UntrustedContent(UntrustedSource::WebPage, '  ')];
        yield 'blank user message' => [static fn () => AiMessage::user('')];
        yield 'empty assistant message' => [static fn () => AiMessage::assistant('')];
        yield 'negative tokens' => [static fn () => new AiTokenUsage(-1, 0)];
        yield 'provider longer than the column of the log' => [static fn () => new AiExternalCost('pricewatch.offer_judgement', str_repeat('a', 33), 'jev-latest', '0.0001')];
        yield 'cost with a comma' => [static fn () => new AiExternalCost('pricewatch.offer_judgement', 'typesafe', 'jev-latest', '0,0001')];
        yield 'negative cost' => [static fn () => new AiExternalCost('pricewatch.offer_judgement', 'typesafe', 'jev-latest', '-1')];
        yield 'currency in lower case' => [static fn () => new AiExternalCost('pricewatch.offer_judgement', 'typesafe', 'jev-latest', '1', 'usd')];
        yield 'external cost with a gateway status' => [static fn () => new AiExternalCost('pricewatch.offer_judgement', 'typesafe', 'jev-latest', '1', status: AiUsageLogStatus::Rejected)];
        yield 'response stopping for tools without any' => [static fn () => self::response('', null, [], new AiTokenUsage(1, 1), '0', 'm', 'c', AiFinishReason::ToolCalls, 1, null)];
        yield 'response without log row' => [static fn () => self::response('ok', null, [], new AiTokenUsage(1, 1), '0', 'm', 'c', AiFinishReason::Completed, 0, null)];
        yield 'failed verdict without reason' => [static fn () => GuardrailVerdict::fail('mistral_moderation', GuardrailStage::Input, ' ')];
        yield 'blank actor' => [static fn () => AiActor::visitor('')];
    }

    #[DataProvider('invalidInputs')]
    public function testTheValueObjectsRefuseInvalidInput(\Closure $build): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        $build();
    }

    private static function usageOf(object $service): AsAiUsage
    {
        $attributes = (new \ReflectionObject($service))->getAttributes(AsAiUsage::class);
        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance();
    }

    /**
     * @param list<AiToolCall>          $toolCalls
     * @param array<string, mixed>|null $data
     */
    private static function response(string $text, ?array $data, array $toolCalls, AiTokenUsage $tokenUsage, string $cost, string $model, string $connection, AiFinishReason $finishReason, int $logId, ?string $generativeUsage): AiResponse
    {
        return new AiResponse(
            text: $text,
            data: $data,
            toolCalls: $toolCalls,
            tokenUsage: $tokenUsage,
            cost: $cost,
            model: $model,
            connection: $connection,
            finishReason: $finishReason,
            guardrailVerdicts: [GuardrailVerdict::pass(CoreGuardrail::Limits->value, GuardrailStage::Input)],
            logId: $logId,
            provenance: null === $generativeUsage ? null : new AiProvenance($generativeUsage, $model, new \DateTimeImmutable('2026-10-28T10:15:00+00:00'), $logId),
        );
    }

    /**
     * Cost in USD with 8 decimals, from tokens and prices per million tokens with 6 decimals, in integers.
     *
     * @param array<int, string> $pricesByTokens
     */
    private static function cost(array $pricesByTokens): string
    {
        $hundredMillionths = 0;

        foreach ($pricesByTokens as $tokens => $price) {
            $micro = (int) str_replace('.', '', $price);
            $hundredMillionths += intdiv($tokens * $micro, 10_000);
        }

        return \sprintf('%d.%08d', intdiv($hundredMillionths, 100_000_000), $hundredMillionths % 100_000_000);
    }
}

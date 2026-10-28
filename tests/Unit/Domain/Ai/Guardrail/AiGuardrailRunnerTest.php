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
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Guardrail\AiGuardrailAvailabilityInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Domain\Ai\Guardrail\GuardrailAvailability;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Model\AiUsageSetting;

final class AiGuardrailRunnerTest extends TestCase
{
    public function testTheUsageDeclaresItsGuardrailsAndTheMerchantReplacesThem(): void
    {
        $runner = new AiGuardrailRunner([]);
        $usage = $this->usage(['moderation' => GuardrailMode::Block, 'topic' => GuardrailMode::Annotate]);

        self::assertSame(['moderation' => GuardrailMode::Block, 'topic' => GuardrailMode::Annotate], $runner->selection($usage, null));
        self::assertSame(['moderation' => GuardrailMode::Block, 'topic' => GuardrailMode::Annotate], $runner->selection($usage, (new AiUsageSetting())->setGuardrails('  ')));
        self::assertSame(['moderation' => GuardrailMode::Block, 'topic' => GuardrailMode::Annotate], $runner->selection($usage, (new AiUsageSetting())->setGuardrails('not json')));
        self::assertSame(['topic' => GuardrailMode::Block], $runner->selection($usage, (new AiUsageSetting())->setGuardrails('{"topic":"block"}')));
        self::assertSame([], $runner->selection($usage, (new AiUsageSetting())->setGuardrails('{}')));
    }

    public function testWhatTheMerchantWritesThatIsNotAGuardrailIsIgnored(): void
    {
        $runner = new AiGuardrailRunner([]);
        $setting = (new AiUsageSetting())->setGuardrails('{"limits":"block","bad name":"block","topic":"shout","ok":"annotate","n":3}');

        self::assertSame(['ok' => GuardrailMode::Annotate], $runner->selection($this->usage(), $setting));
    }

    public function testAFailedCheckIsBlockingOrAnnotatedAsTheModeSaysAndTheBlockingOneStopsTheRun(): void
    {
        $third = $this->guardrail('third', GuardrailVerdict::pass('third', GuardrailStage::Input));
        $runner = new AiGuardrailRunner([
            $this->guardrail('first', GuardrailVerdict::fail('first', GuardrailStage::Input, 'noted')),
            $this->guardrail('second', GuardrailVerdict::fail('second', GuardrailStage::Input, 'flagged')),
            $third,
        ]);

        $verdicts = $runner->run(GuardrailStage::Input, $this->request(), null, ['first' => GuardrailMode::Annotate, 'second' => GuardrailMode::Block, 'third' => GuardrailMode::Block]);

        self::assertSame(['first', 'second'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $verdicts));
        self::assertFalse($verdicts[0]->blocks());
        self::assertSame(GuardrailMode::Annotate, $verdicts[0]->mode);
        self::assertTrue($verdicts[1]->blocks());
        self::assertSame('flagged', $verdicts[1]->reason);
        self::assertSame(0, $third->calls);
    }

    public function testAGuardrailOnlyRunsAtTheStagesItSupportsAndSeesTheOutput(): void
    {
        $inputOnly = $this->guardrail('moderation', GuardrailVerdict::pass('moderation', GuardrailStage::Input), [GuardrailStage::Input]);
        $outputOnly = $this->guardrail('topic', GuardrailVerdict::pass('topic', GuardrailStage::Output), [GuardrailStage::Output]);
        $runner = new AiGuardrailRunner([$inputOnly, $outputOnly]);
        $selection = ['moderation' => GuardrailMode::Block, 'topic' => GuardrailMode::Block];

        $input = $runner->run(GuardrailStage::Input, $this->request(), null, $selection);
        $output = $runner->run(GuardrailStage::Output, $this->request(), 'the answer', $selection);

        self::assertSame(['moderation'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $input));
        self::assertSame(['topic'], array_map(static fn (GuardrailVerdict $verdict): string => $verdict->guardrail, $output));
        self::assertSame([null], $inputOnly->outputs);
        self::assertSame(['the answer'], $outputOnly->outputs);
    }

    public function testAGuardrailThatIsChosenButNotInstalledFailsTheInputCheckInItsMode(): void
    {
        $runner = new AiGuardrailRunner([]);

        $blocking = $runner->run(GuardrailStage::Input, $this->request(), null, ['mistral_moderation' => GuardrailMode::Block]);
        $annotating = $runner->run(GuardrailStage::Input, $this->request(), null, ['mistral_moderation' => GuardrailMode::Annotate]);
        $atOutput = $runner->run(GuardrailStage::Output, $this->request(), 'text', ['mistral_moderation' => GuardrailMode::Block]);

        self::assertTrue($blocking[0]->blocks());
        self::assertSame('unavailable', $blocking[0]->reason);
        self::assertFalse($annotating[0]->blocks());
        self::assertFalse($annotating[0]->passed);
        self::assertSame('unavailable', $annotating[0]->reason);
        self::assertSame([], $atOutput);
    }

    public function testAGuardrailThatThrowsIsAFailedCheckThatNeverQuotesTheError(): void
    {
        $runner = new AiGuardrailRunner([$this->guardrail('moderation', null)]);

        $verdicts = $runner->run(GuardrailStage::Input, $this->request(), null, ['moderation' => GuardrailMode::Block]);

        self::assertTrue($verdicts[0]->blocks());
        self::assertSame('guardrail_error', $verdicts[0]->reason);
    }

    public function testAGuardrailThatThrowsNeverBlocksInAnnotateMode(): void
    {
        $runner = new AiGuardrailRunner([$this->guardrail('moderation', null)]);

        $verdicts = $runner->run(GuardrailStage::Input, $this->request(), null, ['moderation' => GuardrailMode::Annotate]);

        self::assertCount(1, $verdicts);
        self::assertFalse($verdicts[0]->passed);
        self::assertFalse($verdicts[0]->blocks());
        self::assertSame(GuardrailMode::Annotate, $verdicts[0]->mode);
        self::assertSame('guardrail_error', $verdicts[0]->reason);
    }

    public function testAGuardrailThatSaysItIsUnavailableBlocksInBlockModeAndOnlyNotesInAnnotateMode(): void
    {
        $runner = new AiGuardrailRunner([$this->guardrail('moderation', GuardrailVerdict::fail('moderation', GuardrailStage::Input, 'unavailable'))]);

        $blocking = $runner->run(GuardrailStage::Input, $this->request(), null, ['moderation' => GuardrailMode::Block]);
        $annotating = $runner->run(GuardrailStage::Input, $this->request(), null, ['moderation' => GuardrailMode::Annotate]);

        self::assertTrue($blocking[0]->blocks());
        self::assertFalse($annotating[0]->blocks());
        self::assertSame('unavailable', $annotating[0]->reason);
    }

    public function testTheAvailabilityOfTheInstalledGuardrailsComesFromThemWithoutAnyCall(): void
    {
        $plain = $this->guardrail('plain', GuardrailVerdict::pass('plain', GuardrailStage::Input));
        $disconnected = new class implements AiGuardrailInterface, AiGuardrailAvailabilityInterface {
            public int $inspected = 0;

            public function name(): string
            {
                return 'disconnected';
            }

            public function supports(GuardrailStage $stage): bool
            {
                return true;
            }

            public function inspect(GuardrailStage $stage, AiRequest $request, ?string $output): GuardrailVerdict
            {
                ++$this->inspected;

                return GuardrailVerdict::pass('disconnected', $stage);
            }

            public function availability(): GuardrailAvailability
            {
                return GuardrailAvailability::unusable(GuardrailAvailability::NO_CONNECTION);
            }
        };

        $availability = (new AiGuardrailRunner([$plain, $disconnected]))->availability();

        self::assertSame(['plain', 'disconnected'], array_keys($availability));
        self::assertTrue($availability['plain']->usable);
        self::assertFalse($availability['disconnected']->usable);
        self::assertSame('no_connection', $availability['disconnected']->reason);
        self::assertSame(0, $disconnected->inspected);
        self::assertSame(0, $plain->calls);
    }

    public function testAStreamTurnsEveryModeIntoAnnotation(): void
    {
        $runner = new AiGuardrailRunner([$this->guardrail('topic', GuardrailVerdict::fail('topic', GuardrailStage::Output, 'off_topic'))]);

        $verdicts = $runner->run(GuardrailStage::Output, $this->request(), 'text', ['topic' => GuardrailMode::Block], annotateOnly: true);

        self::assertFalse($verdicts[0]->passed);
        self::assertFalse($verdicts[0]->blocks());
        self::assertSame(GuardrailMode::Annotate, $verdicts[0]->mode);
    }

    public function testTheLayerStatesTheNameAndTheStageOfTheVerdict(): void
    {
        $runner = new AiGuardrailRunner([$this->guardrail('topic', GuardrailVerdict::pass('something_else', GuardrailStage::Output))]);

        $verdict = $runner->run(GuardrailStage::Input, $this->request(), null, ['topic' => GuardrailMode::Block])[0];

        self::assertSame(['topic', GuardrailStage::Input], [$verdict->guardrail, $verdict->stage]);
    }

    /**
     * @param array<string, GuardrailMode> $guardrails
     */
    private function usage(array $guardrails = []): AsAiUsage
    {
        return new AsAiUsage('aiprobe.chat', 'Chats.', AiAudience::Public, AiTier::Fast, guardrails: $guardrails);
    }

    private function request(): AiRequest
    {
        return new AiRequest('aiprobe.chat', [AiMessage::user('Hello')]);
    }

    /**
     * @param list<GuardrailStage> $stages
     */
    private function guardrail(string $name, ?GuardrailVerdict $verdict, array $stages = [GuardrailStage::Input, GuardrailStage::Output]): RecordingGuardrail
    {
        return new RecordingGuardrail($name, $verdict, $stages);
    }
}

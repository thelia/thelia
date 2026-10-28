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
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Tests\Support\Ai\AiModerationTestCase;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;

/**
 * What a usage gets from a moderation guardrail, from the settings of the merchant to the log: the mode decides
 * what a flagged text and an unreachable moderation cost.
 */
final class AiModerationThroughGatewayTest extends AiModerationTestCase
{
    public function testAFlaggedMessageIsRefusedInBlockModeBeforeTheModelIsCalledAndTheRefusalIsLogged(): void
    {
        $this->addConnection(AiProvider::Mistral);
        $this->installedGuardrails = [$this->mistral([self::mistralAnswer(['violence_and_threats'])])];
        $this->setting('aiprobe.assistant', '5', guardrails: '{"mistral_moderation":"block"}');
        $gateway = $this->gatewayAnswering([]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'a threat', actor: AiActor::visitor('session-1')));
            self::fail('The moderation blocks.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame(['mistral_moderation', 'flagged: violence_and_threats'], [$blocked->verdict->guardrail, $blocked->verdict->reason]);
        }

        $row = $this->lastLog();
        self::assertSame(['blocked', 'flagged: violence_and_threats', '0.00000000'], [$row->getStatus(), $row->getError(), $row->getCost()]);
        self::assertSame([], $this->sent);
        self::assertSame(0, $this->holds());
    }

    public function testAFlaggedMessageIsLetThroughInAnnotateModeWithANoteInTheLog(): void
    {
        $this->addConnection(AiProvider::Mistral);
        $this->installedGuardrails = [$this->mistral([self::mistralAnswer(['hate_and_discrimination'])])];
        $this->setting('aiprobe.assistant', '5', guardrails: '{"mistral_moderation":"annotate"}');
        $gateway = $this->gatewayAnswering([Wire::chat('Hello')]);

        $response = $gateway->complete($this->request('aiprobe.assistant', 'rude words', actor: AiActor::visitor('session-1')));

        self::assertSame('Hello', $response->text);
        $note = $response->annotations()[0];
        self::assertSame(['mistral_moderation', GuardrailMode::Annotate, 'flagged: hate_and_discrimination'], [$note->guardrail, $note->mode, $note->reason]);
        self::assertSame('succeeded', $this->lastLog()->getStatus());
        self::assertStringContainsString('"guardrail":"mistral_moderation"', (string) $this->lastLog()->getGuardrailVerdicts());
    }

    public function testAModerationThatIsDownBlocksInBlockModeAndNeverBlocksInAnnotateMode(): void
    {
        $this->addConnection(AiProvider::Mistral);
        $down = Wire::json(['message' => 'Service unavailable'], 503);
        $this->installedGuardrails = [$this->mistral([$down, $down, $down])];
        $this->setting('aiprobe.assistant', '5', guardrails: '{"mistral_moderation":"block"}');
        $gateway = $this->gatewayAnswering([Wire::chat('Hello')]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hi', actor: AiActor::visitor('session-1')));
            self::fail('Block mode: an unreachable moderation refuses.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame('unavailable', $blocked->verdict->reason);
        }

        self::assertSame([], $this->sent);

        $this->setting('aiprobe.assistant', '5', guardrails: '{"mistral_moderation":"annotate"}');
        $response = $gateway->complete($this->request('aiprobe.assistant', 'Hi', actor: AiActor::visitor('session-2')));

        self::assertSame('Hello', $response->text);
        self::assertSame(['unavailable', 'unavailable'], array_map(static fn ($verdict): ?string => $verdict->reason, $response->annotations()), 'One note for the input, one for the output.');
        self::assertSame('succeeded', $this->lastLog()->getStatus());
    }

    public function testAnOutputThatTheProviderFlagsIsRefusedInBlockMode(): void
    {
        $this->addConnection(AiProvider::Mistral);
        $this->installedGuardrails = [$this->mistral([self::mistralAnswer(), self::mistralAnswer(['dangerous_and_criminal_content'])])];
        $this->setting('aiprobe.assistant', '5', guardrails: '{"mistral_moderation":"block"}');
        $gateway = $this->gatewayAnswering([Wire::chat('Something harmful', promptTokens: 21, completionTokens: 20)]);

        try {
            $gateway->complete($this->request('aiprobe.assistant', 'Hi', actor: AiActor::visitor('session-1')));
            self::fail('The answer is flagged.');
        } catch (AiGuardrailBlockedException $blocked) {
            self::assertSame($this->lastLog()->getId(), $blocked->logId);
        }

        self::assertSame(['blocked', 'flagged: dangerous_and_criminal_content', '0.00006100'], [$this->lastLog()->getStatus(), $this->lastLog()->getError(), $this->lastLog()->getCost()]);
        self::assertSame(['Hi', 'Something harmful'], array_map(static fn (array $call): string => $call['body']['input'][0], $this->moderationSent));
    }
}

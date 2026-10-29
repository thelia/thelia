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

use AiProbeModule\ProbeWriter;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiFailureExplainer;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Test\IntegrationTestCase;

final class AiFailureExplainerTest extends IntegrationTestCase
{
    public function testAnExceptionIsToldInFrenchAndInEnglishWithItsVariablePart(): void
    {
        $explainer = $this->explainer();
        $failure = new AiProviderUnavailableException('scaleway', true, 'The AI connection "scaleway" failed: 503 Service Unavailable');

        self::assertSame('Le fournisseur de la connexion IA « scaleway » n\'a pas répondu. Réessayez dans quelques minutes.', $explainer->explain($failure, 'fr_FR'));
        self::assertSame('The provider of the AI connection "scaleway" did not answer. Try again in a few minutes.', $explainer->explain($failure, 'en_US'));
    }

    public function testEveryExceptionOfTheLayerHasASentenceInBothLanguagesThatKeepsNothingTechnical(): void
    {
        $verdict = new GuardrailVerdict('topic', GuardrailStage::Input, false, GuardrailMode::Block, 'off_topic');
        $failures = [
            new AiBudgetExceededException('shop.chat'),
            new AiBudgetExceededException('shop.chat', true),
            new AiGuardrailBlockedException($verdict),
            new AiJurisdictionRefusedException('openai', AiJurisdiction::OutsideEuropeanUnion),
            new AiKeyDecryptionException('scaleway'),
            new AiProviderUnavailableException('scaleway', true),
            new AiProviderUnavailableException('scaleway', false, 'The AI connection "scaleway" failed: invalid key sk-secret'),
            new AiRateLimitExceededException('shop.chat'),
            new AiUsageViolationException('shop.chat', 'it did not declare tools'),
            new InactiveAiUsageException('shop.chat'),
            new UnknownAiUsageException('shop.chat'),
            new InvalidAiArgumentException('Another connection already has this code.'),
        ];

        foreach ($failures as $failure) {
            $french = $this->explainer()->explain($failure, 'fr_FR');
            $english = $this->explainer()->explain($failure, 'en_US');

            self::assertNotSame($failure->getMessage(), $french, $failure::class);
            self::assertNotSame($french, $english, $failure::class);
            self::assertStringNotContainsString('sk-secret', $french);
            self::assertStringNotContainsString('%', $french, $failure::class);
        }
    }

    public function testTheBudgetAndTheGuardrailAreToldByTheirKind(): void
    {
        $explainer = $this->explainer();
        $input = new AiGuardrailBlockedException(new GuardrailVerdict('topic', GuardrailStage::Input, false, GuardrailMode::Block, 'off_topic'));
        $output = new AiGuardrailBlockedException(new GuardrailVerdict('moderation', GuardrailStage::Output, false, GuardrailMode::Block, 'flagged: hate'), 3);

        self::assertSame('Le plafond mensuel de toute la couche IA est atteint.', $explainer->explain(new AiBudgetExceededException('shop.chat', true), 'fr_FR'));
        self::assertSame('Le budget mensuel de cet usage est épuisé.', $explainer->explain(new AiBudgetExceededException('shop.chat'), 'fr_FR'));
        self::assertSame('Un contrôle de sécurité a bloqué cette demande.', $explainer->explain($input, 'fr_FR'));
        self::assertSame('Un contrôle de sécurité a bloqué la réponse.', $explainer->explain($output, 'fr_FR'));
    }

    public function testAnExceptionWithoutASentenceKeepsItsOwnMessage(): void
    {
        $failure = new InvalidAiArgumentException('A response points at the row of the usage log the gateway wrote.');

        self::assertSame($failure->getMessage(), $this->explainer()->explain($failure, 'fr_FR'));
    }

    public function testTheFixedRefusalsOfTheSettingsAreTold(): void
    {
        self::assertSame('Une autre connexion porte déjà ce code.', $this->explainer()->explain(new InvalidAiArgumentException('Another connection already has this code.'), 'fr_FR'));
    }

    public function testTheRefusalsOfTheWriterWithAVariablePartAreToldInBothLanguagesWithTheirParts(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
        $writer = new AiSettingsWriter(
            new AiKeyVault(new AiKeyCipher('explainer-test-secret')),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiUsageRegistry(['aiprobe.writing' => ProbeWriter::class], new AiUsageSettingRepository(), new AiSettings()),
            new AiSettings(),
        );

        $cases = [
            [['tier' => 'turbo'], 'La gamme doit être l\'une de : ', 'The tier must be one of: '],
            [['guardrails' => ['probe_moderation' => 'ignore']], 'Le mode du garde-fou « probe_moderation » est', 'The mode of the guardrail "probe_moderation" is'],
            [['guardrails' => ['limits' => 'block']], '« limits » n\'est pas le nom d\'un garde-fou optionnel.', '"limits" is not the name of an optional guardrail.'],
            [['requestsPerMinute' => 0], 'La limite par minute doit être un nombre positif.', 'The limit per minute must be a positive number.'],
            [['promptRetentionDays' => 0], 'La durée de conservation des prompts doit être un nombre positif.', 'The prompt retention must be a positive number.'],
            [['monthlyBudget' => 'a lot'], '« a lot » reçu', '"a lot" given'],
            [['currency' => 'dollars'], '« DOLLARS » n\'est pas un code de devise', '"DOLLARS" is not an ISO 4217 currency code.'],
            [['guardrails' => ['bad name' => 'block']], 'Le nom d\'un garde-fou « bad name » tient en 64', 'A guardrail name "bad name" must hold at most 64'],
        ];

        foreach ($cases as [$override, $french, $english]) {
            try {
                $writer->saveUsageSetting(null, 'aiprobe.writing', ...$override);
                self::fail('Refused: '.json_encode($override));
            } catch (InvalidAiArgumentException $refusal) {
                $inFrench = $this->explainer()->explain($refusal, 'fr_FR');
                $inEnglish = $this->explainer()->explain($refusal, 'en_US');

                self::assertStringContainsString($french, $inFrench, $refusal->getMessage());
                self::assertStringContainsString($english, $inEnglish, $refusal->getMessage());
                self::assertStringNotContainsString('%', $inFrench);
                self::assertStringNotContainsString('%', $inEnglish);
            }
        }

        $tier = null;
        try {
            $writer->saveUsageSetting(null, 'aiprobe.writing', tier: 'turbo');
        } catch (InvalidAiArgumentException $refusal) {
            $tier = $refusal;
        }

        self::assertStringContainsString('gamme', $this->explainer()->explain($tier, 'fr_FR'));
        self::assertStringNotContainsString('must be one of', $this->explainer()->explain($tier, 'fr_FR'));
    }

    private function explainer(): AiFailureExplainer
    {
        return new AiFailureExplainer(new AiTextTranslator(new LangService(new RequestStack(), new NullLogger())));
    }
}

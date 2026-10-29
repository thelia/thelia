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
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Guardrail\TopicGuardrail;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Ai\Service\AiUsageRegister;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Test\IntegrationTestCase;

/**
 * The purpose of a usage is a sentence the merchant reads: the one of a usage the core declares comes in the
 * language of the reader, the one a module declares is shown as the module wrote it.
 */
final class AiUsageRegisterPurposeTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
    }

    public function testThePurposeOfTheUsageOfTheCoreIsTranslated(): void
    {
        $purposes = $this->purposes('fr_FR');

        self::assertSame("Juge si le message d'un visiteur reste dans le sujet de la fonctionnalité à laquelle il est envoyé.", $purposes[TopicGuardrail::USAGE]);
        self::assertSame(TopicGuardrail::PURPOSE, $this->purposes('en_US')[TopicGuardrail::USAGE]);
    }

    public function testThePurposeOfAModuleIsShownAsItWasWritten(): void
    {
        self::assertSame('Writes probe texts.', $this->purposes('fr_FR')['aiprobe.writing']);
    }

    /**
     * @return array<string, string>
     */
    private function purposes(string $locale): array
    {
        $settings = new AiUsageSettingRepository();
        $translator = new AiTextTranslator(new LangService(new RequestStack(), new NullLogger()));
        $registry = new AiUsageRegistry([TopicGuardrail::USAGE => TopicGuardrail::class, 'aiprobe.writing' => ProbeWriter::class], $settings, new AiSettings(), $translator);
        $resolver = new AiTargetResolver($registry, $settings, new AiRoutingRepository(), new AiModelCatalog(), new AiKeyVault(new AiKeyCipher('purpose-test-secret')));

        $purposes = [];

        foreach ((new AiUsageRegister($registry, $settings, $resolver, $translator))->rows($locale) as $row) {
            $purposes[$row['id']] = $row['purpose'];
        }

        return $purposes;
    }
}

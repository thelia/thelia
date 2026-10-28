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
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Compliance\AiActObligation;
use Thelia\Domain\Ai\Compliance\ComplianceState;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

final class AiUsageRegistryTest extends IntegrationTestCase
{
    private AiUsageRegistryInterface $registry;

    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new AiUsageRegistry(
            ['aiprobe.chat' => ProbeWriter::class, 'aiprobe.writing' => ProbeWriter::class],
            new AiUsageSettingRepository(),
            new AiSettings(),
        );
    }

    protected function tearDown(): void
    {
        // The rows roll back with the transaction, the cache of ConfigQuery does not.
        ConfigQuery::write('ai_enabled', '1');

        parent::tearDown();
    }

    public function testItListsAndFindsTheDeclaredUsages(): void
    {
        self::assertSame(['aiprobe.chat', 'aiprobe.writing'], array_map(static fn ($usage): string => $usage->id, $this->registry->all()));
        self::assertSame('Writes probe texts.', $this->registry->get('aiprobe.writing')->purpose);
    }

    public function testAnUnknownUsageIsRefused(): void
    {
        $this->expectException(UnknownAiUsageException::class);

        $this->registry->get('nobody.nothing');
    }

    public function testAnAdminUsageRunsByDefault(): void
    {
        self::assertTrue($this->registry->isActive('aiprobe.writing'));
    }

    public function testTheGlobalSwitchStopsEveryUsage(): void
    {
        $this->setting('aiprobe.writing', budget: '5');
        ConfigQuery::write('ai_enabled', '0');

        self::assertFalse($this->registry->isActive('aiprobe.writing'));
    }

    public function testTheMerchantCanTurnAUsageOff(): void
    {
        $this->setting('aiprobe.writing', enabled: false);

        self::assertFalse($this->registry->isActive('aiprobe.writing'));
    }

    public function testAPublicUsageNeedsABudget(): void
    {
        self::assertFalse($this->registry->isActive('aiprobe.chat'), 'no setting row');

        $this->setting('aiprobe.chat', budget: '0.000000');
        self::assertFalse($this->registry->isActive('aiprobe.chat'), 'budget of zero');

        $this->setting('aiprobe.chat', budget: '12.500000');
        self::assertTrue($this->registry->isActive('aiprobe.chat'));
    }

    public function testComplianceFollowsTheSettingsOfTheMerchant(): void
    {
        $statuses = $this->registry->compliance('aiprobe.chat');
        self::assertCount(1, $statuses);
        self::assertSame(AiActObligation::Interaction, $statuses[0]->obligation);
        self::assertSame(ComplianceState::Met, $statuses[0]->state);

        $this->setting('aiprobe.chat', notice: false);
        self::assertSame(ComplianceState::NotMet, $this->registry->compliance('aiprobe.chat')[0]->state);

        $this->setting('aiprobe.writing', provenance: false);
        self::assertSame(ComplianceState::NotMet, $this->registry->compliance('aiprobe.writing')[0]->state);
    }

    public function testTheNoticeOfAnInteractiveUsageIsTranslatedAndFollowsTheSetting(): void
    {
        $this->setting('aiprobe.chat', budget: '5');

        self::assertSame('You are talking with an AI assistant. Its answers can contain mistakes.', $this->registry->transparencyNotice('aiprobe.chat', 'en_US'));
        self::assertSame('Vous échangez avec un assistant IA. Ses réponses peuvent comporter des erreurs.', $this->registry->transparencyNotice('aiprobe.chat', 'fr_FR'));

        $this->setting('aiprobe.chat', budget: '5', notice: false);

        self::assertNull($this->registry->transparencyNotice('aiprobe.chat', 'en_US'));
    }

    public function testTheNoticeWithoutLocaleIsInTheDefaultLanguageOfTheShopWhenNoRequestRuns(): void
    {
        $this->setting('aiprobe.chat', budget: '5');
        $expected = Lang::getDefaultLanguage()->getLocale();

        self::assertSame($this->registry->transparencyNotice('aiprobe.chat', $expected), $this->registry->transparencyNotice('aiprobe.chat'));
    }

    public function testAnInactiveUsageHasNoNotice(): void
    {
        $this->setting('aiprobe.chat', budget: '5');
        self::assertNotNull($this->registry->transparencyNotice('aiprobe.chat', 'en_US'));

        $this->setting('aiprobe.chat', budget: '0.000000');
        self::assertNull($this->registry->transparencyNotice('aiprobe.chat', 'en_US'), 'a public usage without budget is inactive');

        $this->setting('aiprobe.chat', enabled: false, budget: '5');
        self::assertNull($this->registry->transparencyNotice('aiprobe.chat', 'en_US'), 'turned off by the merchant');
    }

    public function testAUsageWithoutNoticeStaysActiveAndSaysWhyItDoesNotComply(): void
    {
        $this->setting('aiprobe.chat', budget: '5', notice: false);

        self::assertTrue($this->registry->isActive('aiprobe.chat'), 'the core never blocks');

        $status = $this->registry->compliance('aiprobe.chat')[0];
        self::assertSame(ComplianceState::NotMet, $status->state);
        self::assertSame('notice_turned_off', $status->reason);
        self::assertStringContainsString('does not comply with article 50', $status->message);
        self::assertStringContainsString('Cet usage ne respecte pas l\'article 50', $status->translate(Translator::getInstance(), 'fr_FR'));
        self::assertStringContainsString('aiprobe.chat', $status->translate(Translator::getInstance(), 'fr_FR'), 'the placeholder is filled');
    }

    public function testAUsageThatIsNotInteractiveHasNoNotice(): void
    {
        self::assertNull($this->registry->transparencyNotice('aiprobe.writing'));
    }

    public function testTheNoticeOfAnUnknownUsageIsRefused(): void
    {
        $this->expectException(UnknownAiUsageException::class);

        $this->registry->transparencyNotice('nobody.nothing');
    }

    private function setting(string $usageId, bool $enabled = true, ?string $budget = null, bool $notice = true, bool $provenance = true): void
    {
        $setting = AiUsageSettingQuery::create()->findOneByUsageId($usageId) ?? (new AiUsageSetting())->setUsageId($usageId);
        $setting->setEnabled($enabled)->setMonthlyBudget($budget)->setTransparencyNotice($notice)->setProvenanceMark($provenance)->save();
    }
}

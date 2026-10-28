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

    private function setting(string $usageId, bool $enabled = true, ?string $budget = null, bool $notice = true, bool $provenance = true): void
    {
        $setting = AiUsageSettingQuery::create()->findOneByUsageId($usageId) ?? (new AiUsageSetting())->setUsageId($usageId);
        $setting->setEnabled($enabled)->setMonthlyBudget($budget)->setTransparencyNotice($notice)->setProvenanceMark($provenance)->save();
    }
}

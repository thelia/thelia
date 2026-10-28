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

namespace Thelia\Tests\Integration\Command;

use AiProbeModule\FormulaWriter;
use AiProbeModule\ProbeWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Command\AiUsagesCommand;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiTextTranslator;
use Thelia\Domain\Ai\Service\AiUsageRegister;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;
use Thelia\Test\IntegrationTestCase;

final class AiUsagesCommandTest extends IntegrationTestCase
{
    private AiUsagesCommand $command;

    private AiUsagesCommand $formulaCommand;

    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 2).'/fixtures/ai/ProbeModule/ProbeWriter.php';
        require_once \dirname(__DIR__, 2).'/fixtures/ai/ProbeModule/FormulaWriter.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $settings = new AiUsageSettingRepository();
        $translator = new AiTextTranslator(new RequestStack());
        $registry = new AiUsageRegistry(['aiprobe.writing' => ProbeWriter::class, 'aiprobe.chat' => ProbeWriter::class], $settings, new AiSettings(), $translator);
        $resolver = new AiTargetResolver($registry, $settings, new AiRoutingRepository(), new AiModelCatalog(), new AiKeyVault(new AiKeyCipher('usages-test-secret')));
        $this->command = new AiUsagesCommand(new AiUsageRegister($registry, $settings, $resolver, $translator));

        $formulas = new AiUsageRegistry(['aiprobe.formula' => FormulaWriter::class, 'aiprobe.undeclared' => FormulaWriter::class], $settings, new AiSettings(), $translator);
        $this->formulaCommand = new AiUsagesCommand(new AiUsageRegister($formulas, $settings, new AiTargetResolver($formulas, $settings, new AiRoutingRepository(), new AiModelCatalog(), new AiKeyVault(new AiKeyCipher('usages-test-secret'))), $translator));

        AiTierAssignmentQuery::create()->deleteAll();
    }

    public function testACellThatASpreadsheetWouldReadAsAFormulaIsNeutralisedInTheCsv(): void
    {
        $tester = new CommandTester($this->formulaCommand);
        $tester->execute(['--format' => 'csv']);

        self::assertStringContainsString("\"'=HYPERLINK(", $tester->getDisplay());
        self::assertStringContainsString('to_review', $tester->getDisplay(), 'a public usage that declares nothing is to review');
        self::assertStringContainsString('not_applicable', $tester->getDisplay(), 'an admin usage without duty');
    }

    public function testTheJsonRegisterListsEveryDeclaredUsageWithItsCompliance(): void
    {
        $this->setting('aiprobe.chat', budget: '5', notice: false);
        $connection = (new AiConnection())->setCode('reg-mistral')->setTitle('x')->setProvider('mistral')->setJurisdiction('eu')->setActive(true);
        $connection->save();
        $model = (new AiModel())->setProvider('mistral')->setName('ministral-reg')->setInputPrice('1.000000')->setOutputPrice('2.000000')->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();
        (new AiTierAssignment())->setTier(AiTier::Fast->value)->setConnectionId($connection->getId())->setModelId($model->getId())->save();

        $tester = $this->execute(['--format' => 'json', '--locale' => 'fr_FR']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $rows = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['aiprobe.writing', 'aiprobe.chat'], array_column($rows, 'id'));

        [$writing, $chat] = $rows;
        self::assertSame('aiprobe', $chat['module']);
        self::assertSame('Chats with visitors.', $chat['purpose']);
        self::assertSame('public', $chat['audience']);
        self::assertSame('fast', $chat['tier']);
        self::assertSame('interactive', $chat['transparency']);
        self::assertTrue($chat['active'], 'the usage stays active without its notice');
        self::assertFalse($chat['notice_enabled']);
        self::assertTrue($chat['provenance_mark_enabled']);
        self::assertSame('not_met', $chat['compliance']);
        self::assertSame('eu', $chat['jurisdiction']);
        self::assertSame('article_50_1', $chat['compliance_details'][0]['obligation']);
        self::assertSame('notice_turned_off', $chat['compliance_details'][0]['reason']);
        self::assertStringContainsString('ne respecte pas l\'article 50', $chat['compliance_details'][0]['message']);

        self::assertSame('met', $writing['compliance']);
        self::assertTrue($writing['notice_enabled']);
    }

    public function testTheRegisterShowsTheTierTheMerchantChoseAndTheOneTheModuleDeclared(): void
    {
        $this->setting('aiprobe.writing', budget: null, notice: true, provenance: true);
        AiUsageSettingQuery::create()->findOneByUsageId('aiprobe.writing')->setTier(AiTier::Advanced->value)->save();

        $rows = json_decode($this->execute(['--format' => 'json'])->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['advanced', 'fast'], [$rows[0]['tier'], $rows[0]['declared_tier']]);
        self::assertSame(['fast', 'fast'], [$rows[1]['tier'], $rows[1]['declared_tier']], 'a usage without a choice runs on the declared tier');
    }

    public function testWithoutAConnectionTheJurisdictionIsEmpty(): void
    {
        $rows = json_decode($this->execute(['--format' => 'json'])->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNull($rows[0]['jurisdiction']);
    }

    public function testTheCsvHasOneLinePerUsageAndTheExplanation(): void
    {
        $this->setting('aiprobe.writing', budget: null, notice: true, provenance: false);

        $lines = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), explode("\n", trim($this->execute(['--format' => 'csv', '--locale' => 'en_US'])->getDisplay())));

        self::assertSame(['id', 'module', 'purpose', 'audience', 'tier', 'declared_tier', 'transparency', 'notice_enabled', 'provenance_mark_enabled', 'active', 'compliance', 'jurisdiction', 'compliance_explanation'], $lines[0]);
        self::assertCount(3, $lines);
        self::assertSame(['aiprobe.writing', 'aiprobe', 'Writes probe texts.', 'admin', 'fast', 'fast', 'generative', 'yes', 'no', 'yes', 'not_met', ''], \array_slice($lines[1], 0, 12));
        self::assertStringContainsString('does not comply with article 50', $lines[1][12]);
        self::assertSame('', $lines[2][12], 'nothing to explain when the duty is met');
    }

    public function testTheTableIsTheDefaultFormat(): void
    {
        $display = $this->execute([])->getDisplay();

        self::assertStringContainsString('aiprobe.chat', $display);
        self::assertStringContainsString('compliance', $display);
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $tester = $this->execute(['--format' => 'xml']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('table, json, csv', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $options
     */
    private function execute(array $options): CommandTester
    {
        $tester = new CommandTester($this->command);
        $tester->execute($options);

        return $tester;
    }

    private function setting(string $usageId, ?string $budget = null, bool $notice = true, bool $provenance = true): void
    {
        $setting = AiUsageSettingQuery::create()->findOneByUsageId($usageId) ?? (new AiUsageSetting())->setUsageId($usageId);
        $setting->setMonthlyBudget($budget)->setTransparencyNotice($notice)->setProvenanceMark($provenance)->save();
    }
}

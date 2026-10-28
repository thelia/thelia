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
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageSettingQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class AiSettingsWriterTest extends IntegrationTestCase
{
    private AiKeyVault $vault;

    private AiSettingsWriter $writer;

    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->vault = new AiKeyVault(new AiKeyCipher('writer-test-secret'));
        $this->writer = new AiSettingsWriter(
            $this->vault,
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiUsageRegistry(['aiprobe.writing' => ProbeWriter::class], new AiUsageSettingRepository(), new AiSettings()),
            new AiSettings(),
        );

        AiTierAssignmentQuery::create()->deleteAll();
    }

    protected function tearDown(): void
    {
        // The rows roll back with the transaction, the cache of ConfigQuery does not.
        ConfigQuery::write('ai_enabled', '1');
        ConfigQuery::write('ai_eu_only', '0');
        ConfigQuery::write('ai_monthly_cap', '0');

        parent::tearDown();
    }

    public function testAConnectionIsCreatedWithItsKeyInTheVaultOnly(): void
    {
        $connection = $this->connection('mistral-main', apiKey: 'sk-writer-0123456789');

        self::assertSame('eu', $connection->getJurisdiction(), 'the provider tells where it processes the data');
        self::assertStringNotContainsString('sk-writer', (string) $connection->getApiKeyEncrypted());
        self::assertSame('sk-writer-0123456789', $this->vault->resolve($connection));
        self::assertNotNull(AiConnectionQuery::create()->findOneByCode('mistral-main'));
    }

    public function testABlankKeyKeepsTheStoredOneAndAnotherCodeIsRefused(): void
    {
        $connection = $this->connection('mistral-main', apiKey: 'sk-writer-0123456789');

        $updated = $this->connection('mistral-main', title: 'Renamed', apiKey: '  ', existing: $connection);

        self::assertSame($connection->getId(), $updated->getId());
        self::assertSame('Renamed', $updated->getTitle());
        self::assertSame('sk-writer-0123456789', $this->vault->resolve($updated));

        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('Another connection already has this code.');

        $this->connection('mistral-main');
    }

    public function testTheStoredKeyNeverFollowsAConnectionToAnotherServer(): void
    {
        $connection = $this->connection('compatible', provider: 'openai_compatible', jurisdiction: 'eu', baseUrl: 'https://93.184.216.34/v1', apiKey: 'sk-writer-0123456789');

        try {
            $this->connection('compatible', provider: 'openai_compatible', jurisdiction: 'eu', baseUrl: 'https://1.1.1.1/v1', existing: $connection);
            self::fail('A new address without the key again is refused.');
        } catch (InvalidAiArgumentException $refusal) {
            self::assertStringContainsString('type its API key again', $refusal->getMessage());
        }

        $stored = AiConnectionQuery::create()->findOneByCode('compatible');
        self::assertSame('https://93.184.216.34/v1', $stored->getBaseUrl());
        self::assertSame('sk-writer-0123456789', $this->vault->resolve($stored));

        $moved = $this->connection('compatible', provider: 'openai_compatible', jurisdiction: 'eu', baseUrl: 'https://1.1.1.1/v1', apiKey: 'sk-other-9876543210', existing: $stored);

        self::assertSame('https://1.1.1.1/v1', $moved->getBaseUrl());
        self::assertSame('sk-other-9876543210', $this->vault->resolve($moved));
    }

    public function testTheProviderOfAConnectionATierReliesOnDoesNotChange(): void
    {
        $connection = $this->connection('mistral-main');
        $this->writer->assignTier(null, 'fast', $connection->getId(), $this->model($connection, 'ministral-a')->getId());

        try {
            $this->connection('mistral-main', provider: 'anthropic', existing: $connection);
            self::fail('The provider of a connection a tier relies on does not change.');
        } catch (InvalidAiArgumentException) {
        }

        self::assertSame('mistral', AiConnectionQuery::create()->findOneByCode('mistral-main')->getProvider());

        $unused = $this->connection('mistral-spare');
        self::assertSame('anthropic', $this->connection('mistral-spare', provider: 'anthropic', existing: $unused)->getProvider());
    }

    public function testTheProviderOfAConnectionAUsageIsPinnedToKeepsServingTheModelOfItsTier(): void
    {
        $tierConnection = $this->connection('mistral-main');
        $this->writer->assignTier(null, 'fast', $tierConnection->getId(), $this->model($tierConnection, 'ministral-a')->getId());

        $pinned = $this->connection('mistral-pinned');
        $setting = $this->writer->saveUsageSetting(null, 'aiprobe.writing', pinnedConnectionId: $pinned->getId());

        try {
            $this->connection('mistral-pinned', provider: 'anthropic', existing: $pinned);
            self::fail('The provider of a connection a usage runs the model of its declared tier on does not change.');
        } catch (InvalidAiArgumentException) {
        }

        self::assertSame('mistral', AiConnectionQuery::create()->findOneByCode('mistral-pinned')->getProvider());

        $this->writer->saveUsageSetting($setting, 'aiprobe.writing', tier: 'advanced', pinnedConnectionId: $pinned->getId());
        self::assertSame('anthropic', $this->connection('mistral-pinned', provider: 'anthropic', existing: $pinned)->getProvider(), 'a tier without a model leaves nothing to keep');
    }

    public function testAConnectionThatBreaksARuleIsRefusedAndNothingIsSaved(): void
    {
        foreach ([
            ['code' => 'Bad Code'],
            ['title' => ' '],
            ['provider' => 'nobody'],
            ['provider' => 'openai_compatible', 'jurisdiction' => null],
            ['apiKeyEnv' => '1 bad'],
            ['provider' => 'openai_compatible', 'jurisdiction' => 'eu', 'baseUrl' => 'http://127.0.0.1:9999'],
        ] as $override) {
            try {
                $this->connection(...array_merge(['code' => 'refused'], $override));
                self::fail('Refused: '.json_encode($override));
            } catch (InvalidAiArgumentException) {
            }
        }

        self::assertSame(0, AiConnectionQuery::create()->filterByCode('refused')->count());
    }

    public function testATierIsAssignedOnceAndMovedByItsAssignment(): void
    {
        $connection = $this->connection('mistral-main');
        $first = $this->model($connection, 'ministral-a');
        $second = $this->model($connection, 'ministral-b');

        $assignment = $this->writer->assignTier(null, 'fast', $connection->getId(), $first->getId());
        $moved = $this->writer->assignTier($assignment, 'fast', $connection->getId(), $second->getId());

        self::assertSame($assignment->getId(), $moved->getId());
        self::assertSame($second->getId(), AiTierAssignmentQuery::create()->findOneByTier('fast')->getModelId());

        try {
            $this->writer->assignTier(null, 'fast', $connection->getId(), $first->getId());
            self::fail('A tier has one assignment.');
        } catch (InvalidAiArgumentException $refusal) {
            self::assertStringContainsString('already has an assignment', $refusal->getMessage());
        }

        $other = $this->connection('mistral-second');
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('The model is not served by this connection.');

        $this->writer->assignTier(null, 'standard', $other->getId(), $this->model($connection, 'bound', connectionId: $connection->getId())->getId());
    }

    public function testAUsageSettingKeepsTheTierTheBudgetTheSwitchesAndTheNoticeAndMarkChoices(): void
    {
        $setting = $this->writer->saveUsageSetting(null, 'aiprobe.writing', enabled: false, tier: 'advanced', monthlyBudget: ' 12.5 ', transparencyNotice: false, provenanceMark: false, requestsPerDay: 40, guardrails: ['probe_moderation' => 'annotate']);

        $stored = AiUsageSettingQuery::create()->findPk($setting->getId());
        self::assertSame([false, 'advanced', '12.500000', false, false, 40], [(bool) $stored->getEnabled(), $stored->getTier(), $stored->getMonthlyBudget(), (bool) $stored->getTransparencyNotice(), (bool) $stored->getProvenanceMark(), $stored->getRequestsPerDay()]);
        self::assertSame(['probe_moderation' => 'annotate'], $stored->getGuardrailMap());

        $this->writer->saveUsageSetting($stored, 'aiprobe.writing', tier: null);

        self::assertNull(AiUsageSettingQuery::create()->findPk($setting->getId())->getTier(), 'no tier gives the declared one back');
        self::assertSame(1, AiUsageSettingQuery::create()->filterByUsageId('aiprobe.writing')->count());
    }

    public function testAUsageSettingThatBreaksARuleIsRefused(): void
    {
        foreach ([
            ['tier' => 'turbo'],
            ['monthlyBudget' => 'a lot'],
            ['currency' => 'dollars'],
            ['maxOutputTokens' => 0],
            ['pinnedConnectionId' => 999999],
            ['pinnedModelId' => 999999],
            ['guardrails' => ['probe_moderation' => 'ignore']],
        ] as $override) {
            try {
                $this->writer->saveUsageSetting(null, 'aiprobe.writing', ...$override);
                self::fail('Refused: '.json_encode($override));
            } catch (InvalidAiArgumentException) {
            }
        }

        self::assertSame(0, AiUsageSettingQuery::create()->count());

        $this->writer->saveUsageSetting(null, 'aiprobe.writing');

        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('already has settings');

        $this->writer->saveUsageSetting(null, 'aiprobe.writing');
    }

    public function testAnUndeclaredUsageIsRefused(): void
    {
        $this->expectException(UnknownAiUsageException::class);

        $this->writer->saveUsageSetting(null, 'nobody.declared');
    }

    public function testTheGlobalSettingsAreWritten(): void
    {
        $settings = new AiSettings();

        $this->writer->setEnabled(false);
        $this->writer->setEuropeanUnionOnly(true);
        $this->writer->setMonthlyCap('25.00');

        self::assertFalse($settings->isEnabled());
        self::assertTrue($settings->isEuropeanUnionOnly());
        self::assertSame('25.00', $settings->monthlyCap());

        $this->expectException(InvalidAiArgumentException::class);

        $this->writer->setMonthlyCap('lots');
    }

    private function connection(string $code, string $title = 'Main', string $provider = 'mistral', ?string $jurisdiction = null, ?string $baseUrl = null, ?string $apiKey = null, ?string $apiKeyEnv = null, ?AiConnection $existing = null): AiConnection
    {
        return $this->writer->saveConnection($existing, $code, $title, $provider, $jurisdiction, $baseUrl, $apiKey, $apiKeyEnv, false, true);
    }

    private function model(AiConnection $connection, string $name, ?int $connectionId = null): AiModel
    {
        $model = (new AiModel())->setProvider((string) $connection->getProvider())->setName($name)->setConnectionId($connectionId)->setInputPrice('1.000000')->setOutputPrice('2.000000')->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();

        return $model;
    }
}

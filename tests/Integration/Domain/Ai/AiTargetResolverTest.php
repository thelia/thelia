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
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
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

final class AiTargetResolverTest extends IntegrationTestCase
{
    private const string ENVIRONMENT_VARIABLE = 'THELIA_AI_RESOLVER_TEST_KEY';

    private AiKeyVault $vault;

    private AiTargetResolver $resolver;

    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->vault = new AiKeyVault(new AiKeyCipher('resolver-test-secret'));
        $this->resolver = new AiTargetResolver(
            new AiUsageRegistry(['aiprobe.writing' => ProbeWriter::class, 'aiprobe.chat' => ProbeWriter::class], new AiUsageSettingRepository(), new AiSettings()),
            new AiUsageSettingRepository(),
            new AiRoutingRepository(),
            new AiModelCatalog(),
            $this->vault,
        );

        AiTierAssignmentQuery::create()->deleteAll();
    }

    protected function tearDown(): void
    {
        unset($_SERVER[self::ENVIRONMENT_VARIABLE], $_ENV[self::ENVIRONMENT_VARIABLE]);
        putenv(self::ENVIRONMENT_VARIABLE);

        parent::tearDown();
    }

    public function testTheTierOfTheUsageGivesTheConnectionTheModelAndTheKey(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu', apiKey: 'sk-stored-0123');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe', '0.100000', '0.300000'));

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertSame($connection->getId(), $target->connectionId);
        self::assertSame('mistral-main', $target->connectionCode);
        self::assertSame(AiProvider::Mistral, $target->provider);
        self::assertNull($target->baseUrl);
        self::assertSame('sk-stored-0123', $target->apiKey());
        self::assertSame('ministral-probe', $target->model->name);
        self::assertSame('0.100000', $target->model->inputPrice);
        self::assertSame(AiJurisdiction::EuropeanUnion, $target->jurisdiction);
        self::assertFalse($target->allowPrivateNetwork);
    }

    public function testAModelThatTheEmbeddedListNoLongerHoldsKeepsRunningFromItsRow(): void
    {
        // Retired from the embedded list in 3.3.0 (the provider does not serve it): a range assigned to it before keeps its price and its settings.
        self::assertNull((new AiModelCatalog())->find('mistral', 'codestral-mamba-latest'));
        $connection = $this->connection('mistral-main', 'mistral', 'eu', apiKey: 'sk-stored-0123');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'codestral-mamba-latest', '0.250000', '0.250000'));

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertSame('codestral-mamba-latest', $target->model->name);
        self::assertSame('0.250000', $target->model->inputPrice);
    }

    public function testTheKeyOfTheEnvironmentWinsOverTheStoredOne(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu', apiKey: 'sk-stored-0123', keyEnvironment: self::ENVIRONMENT_VARIABLE);
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));
        $_SERVER[self::ENVIRONMENT_VARIABLE] = 'sk-from-env-4567';

        self::assertSame('sk-from-env-4567', $this->resolver->resolve('aiprobe.writing')->apiKey());
    }

    public function testNoKeyAtAllIsANullKeyForTheAdapterToJudge(): void
    {
        $connection = $this->connection('lan-ollama', 'ollama', 'eu', baseUrl: 'http://192.168.1.20:11434', privateNetwork: true);
        $this->assign(AiTier::Fast, $connection, $this->model('ollama', 'llama-probe', connectionId: $connection->getId()));

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertNull($target->apiKey());
        self::assertTrue($target->allowPrivateNetwork);
        self::assertSame('http://192.168.1.20:11434', $target->baseUrl);
    }

    public function testAKeyThatCannotBeDecryptedIsAnErrorThatNamesTheConnectionButNotTheKey(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu');
        $connection->setApiKeyEncrypted('garbage-that-is-not-a-ciphertext')->save();
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));

        try {
            $this->resolver->resolve('aiprobe.writing');
            self::fail('The resolution should have failed.');
        } catch (AiKeyDecryptionException $exception) {
            self::assertStringContainsString('mistral-main', $exception->getMessage());
            self::assertStringNotContainsString('garbage', $exception->getMessage());
        }
    }

    public function testTheModelPinnedOnTheUsageWinsOverTheTier(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));
        $pinned = $this->model('mistral', 'mistral-large-probe');
        $this->pin('aiprobe.writing', model: $pinned);

        self::assertSame('mistral-large-probe', $this->resolver->resolve('aiprobe.writing')->model->name);
    }

    public function testTheModelPinnedOnAConnectionGoesThroughThatConnection(): void
    {
        $tier = $this->connection('mistral-main', 'mistral', 'eu');
        $lan = $this->connection('lan-ollama', 'ollama', 'eu', baseUrl: 'http://192.168.1.20:11434', privateNetwork: true);
        $this->assign(AiTier::Fast, $tier, $this->model('mistral', 'ministral-probe'));
        $this->pin('aiprobe.writing', model: $this->model('ollama', 'llama-probe', connectionId: $lan->getId()));

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertSame('lan-ollama', $target->connectionCode);
        self::assertSame('llama-probe', $target->model->name);
    }

    public function testAPinnedConnectionKeepsTheModelOfTheTierOnlyForTheSameProvider(): void
    {
        $tier = $this->connection('mistral-main', 'mistral', 'eu');
        $second = $this->connection('mistral-second', 'mistral', 'eu');
        $this->assign(AiTier::Fast, $tier, $this->model('mistral', 'ministral-probe'));
        $this->pin('aiprobe.writing', connection: $second);

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertSame('mistral-second', $target->connectionCode);
        self::assertSame('ministral-probe', $target->model->name);

        $other = $this->connection('anthropic-main', 'anthropic', 'non_eu');
        $this->pin('aiprobe.writing', connection: $other);

        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testTheTierTheMerchantChoseForTheUsageReplacesTheOneTheModuleDeclared(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));
        $this->assign(AiTier::Advanced, $connection, $this->model('mistral', 'mistral-large-probe'));

        self::assertSame('ministral-probe', $this->resolver->resolve('aiprobe.writing')->model->name);

        $setting = (new AiUsageSetting())->setUsageId('aiprobe.writing')->setTier(AiTier::Advanced->value);
        $setting->save();

        self::assertSame('mistral-large-probe', $this->resolver->resolve('aiprobe.writing')->model->name);
        self::assertSame('ministral-probe', $this->resolver->resolve('aiprobe.chat')->model->name, 'another usage keeps its own tier');

        $setting->setTier('no-such-tier')->save();

        self::assertSame('ministral-probe', $this->resolver->resolve('aiprobe.writing')->model->name, 'a stored value that is not a tier gives the declared one back');
    }

    public function testAChosenTierWithoutAssignmentStopsTheUsage(): void
    {
        $this->assign(AiTier::Fast, $this->connection('mistral-main', 'mistral', 'eu'), $this->model('mistral', 'ministral-probe'));
        (new AiUsageSetting())->setUsageId('aiprobe.writing')->setTier(AiTier::Standard->value)->save();

        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testAnInactiveConnectionStopsTheUsage(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu', active: false);
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));

        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testAnInactiveModelStopsTheUsage(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe', active: false));

        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testATierWithoutAssignmentStopsTheUsage(): void
    {
        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testAnAssignmentWhereTheModelBelongsToAnotherProviderOrConnectionStopsTheUsage(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu');
        $this->assign(AiTier::Fast, $connection, $this->model('anthropic', 'claude-probe'));

        try {
            $this->resolver->resolve('aiprobe.writing');
            self::fail('A model of another provider must not be sent to this connection.');
        } catch (InactiveAiUsageException) {
        }

        $lan = $this->connection('lan-ollama', 'ollama', 'eu');
        $otherLan = $this->connection('other-ollama', 'ollama', 'eu');
        // The same name exists for every connection: the catalogue would answer with it, the assignment points elsewhere.
        $this->model('ollama', 'llama-probe');
        AiTierAssignmentQuery::create()->deleteAll();
        $this->assign(AiTier::Fast, $lan, $this->model('ollama', 'llama-probe', connectionId: $otherLan->getId()));

        $this->expectException(InactiveAiUsageException::class);

        $this->resolver->resolve('aiprobe.writing');
    }

    public function testAnUnknownUsageIsRefused(): void
    {
        $this->expectException(UnknownAiUsageException::class);

        $this->resolver->resolve('nobody.nothing');
    }

    public function testTheJurisdictionFollowsTheProviderAndTheAddress(): void
    {
        $eu = $this->connection('openai-eu', 'openai', 'non_eu', baseUrl: 'https://eu.api.openai.com');
        $world = $this->connection('openai-world', 'openai', 'eu');
        $anthropic = $this->connection('anthropic-main', 'anthropic', 'eu');
        $ollama = $this->connection('lan-ollama', 'ollama', 'eu', baseUrl: 'http://192.168.1.20:11434', privateNetwork: true);
        $ollamaUndeclared = $this->connection('lan-other', 'ollama', 'bogus', baseUrl: 'http://192.168.1.21:11434', privateNetwork: true);

        $expected = [
            [$eu, 'gpt-probe', 'openai', AiJurisdiction::EuropeanUnion],
            [$world, 'gpt-probe', 'openai', AiJurisdiction::OutsideEuropeanUnion],
            [$anthropic, 'claude-probe', 'anthropic', AiJurisdiction::OutsideEuropeanUnion],
            [$ollama, 'llama-probe', 'ollama', AiJurisdiction::EuropeanUnion],
            [$ollamaUndeclared, 'llama-probe', 'ollama', AiJurisdiction::OutsideEuropeanUnion],
        ];

        foreach ($expected as [$connection, $name, $provider, $jurisdiction]) {
            AiTierAssignmentQuery::create()->deleteAll();
            $this->assign(AiTier::Fast, $connection, $this->model($provider, $name.$connection->getId()));

            self::assertSame($jurisdiction, $this->resolver->resolve('aiprobe.writing')->jurisdiction, $connection->getCode());
        }
    }

    public function testTheTargetNeverShowsItsKeyWhenItIsDumped(): void
    {
        $connection = $this->connection('mistral-main', 'mistral', 'eu', apiKey: 'sk-stored-0123');
        $this->assign(AiTier::Fast, $connection, $this->model('mistral', 'ministral-probe'));

        $target = $this->resolver->resolve('aiprobe.writing');

        self::assertStringNotContainsString('sk-stored-0123', print_r($target, true));
        self::assertStringNotContainsString('sk-stored-0123', var_export($target->__debugInfo(), true));
        self::assertStringNotContainsString('sk-stored-0123', serialize($target->__debugInfo()));
    }

    private function connection(string $code, string $provider, string $jurisdiction, ?string $baseUrl = null, bool $privateNetwork = false, bool $active = true, ?string $apiKey = null, ?string $keyEnvironment = null): AiConnection
    {
        $connection = (new AiConnection())
            ->setCode($code)
            ->setTitle($code)
            ->setProvider($provider)
            ->setJurisdiction($jurisdiction)
            ->setBaseUrl($baseUrl)
            ->setAllowPrivateNetwork($privateNetwork)
            ->setActive($active)
            ->setApiKeyEncrypted(null === $apiKey ? null : $this->vault->encrypt($apiKey))
            ->setApiKeyEnv($keyEnvironment);
        $connection->save();

        return $connection;
    }

    private function model(string $provider, string $name, string $input = '1.000000', string $output = '2.000000', bool $active = true, ?int $connectionId = null): AiModel
    {
        $model = (new AiModel())
            ->setProvider($provider)
            ->setName($name)
            ->setConnectionId($connectionId)
            ->setInputPrice($input)
            ->setOutputPrice($output)
            ->setActive($active)
            ->setSource(AiModelSpecification::SOURCE_MANUAL);
        $model->save();

        return $model;
    }

    private function assign(AiTier $tier, AiConnection $connection, AiModel $model): void
    {
        (new AiTierAssignment())->setTier($tier->value)->setConnectionId($connection->getId())->setModelId($model->getId())->save();
    }

    private function pin(string $usageId, ?AiConnection $connection = null, ?AiModel $model = null): void
    {
        $setting = (AiUsageSettingQuery::create()->findOneByUsageId($usageId) ?? (new AiUsageSetting())->setUsageId($usageId))->setPinnedConnectionId($connection?->getId())->setPinnedModelId($model?->getId());
        $setting->save();
    }
}

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

namespace Thelia\Tests\Support\Ai;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Catalog\AiCostCalculator;
use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Domain\Ai\Gateway\AiAdmission;
use Thelia\Domain\Ai\Gateway\AiGateway;
use Thelia\Domain\Ai\Gateway\AiPromptSnapshot;
use Thelia\Domain\Ai\Gateway\AiRequestMeasure;
use Thelia\Domain\Ai\Guardrail\AiCoreOutputGuardrails;
use Thelia\Domain\Ai\Guardrail\AiGuardrailInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Domain\Ai\Guardrail\AiHtmlSanitizer;
use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiMessageBagFactory;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderErrorTranslator;
use Thelia\Domain\Ai\Provider\AiProviderPresets;
use Thelia\Domain\Ai\Provider\AiResultReader;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Thelia\Domain\Ai\Provider\UntrustedContentDelimiter;
use Thelia\Domain\Ai\Repository\AiRoutingRepository;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiRateLimiter;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\AiBudgetReservationQuery;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignment;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageLog;
use Thelia\Model\AiUsageLogQuery;
use Thelia\Model\AiUsageSetting;
use Thelia\Model\AiUsageSettingQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The gateway over the real database and the real adapter of an OpenAI-compatible connection, with a mock
 * transport in place of the network: what the provider receives is recorded, nothing leaves the machine.
 *
 * Money in the tests: the model costs 1.00 USD per million input tokens and 2.00 per million output tokens.
 */
abstract class AiGatewayTestCase extends IntegrationTestCase
{
    public const string PROBE_ACTOR_SECRET = 'gateway-test-secret';

    /** A public address: the anti-SSRF client accepts it without looking a name up. */
    public const string PUBLIC_URL = 'https://93.184.216.34';

    /** @var list<array{method: string, url: string, body: array<string, mixed>|null, held: string}> */
    protected array $sent = [];

    protected AiGateway $gateway;

    protected AiBudgetLedger $ledger;

    protected AiActorHasher $hasher;

    protected CountingActorResolver $resolver;

    protected AiConnection $connection;

    protected AiModel $model;

    /** @var list<AiGuardrailInterface> */
    protected array $installedGuardrails = [];

    private AiKeyVault $vault;

    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 2).'/fixtures/ai/ProbeModule/ProbeGateway.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->vault = new AiKeyVault(new AiKeyCipher('gateway-test-secret'));
        $this->hasher = new AiActorHasher(self::PROBE_ACTOR_SECRET);
        $this->ledger = new AiBudgetLedger();
        $this->resolver = new CountingActorResolver(AiActor::visitor('resolved-visitor'));
        AiTierAssignmentQuery::create()->deleteAll();
        $this->connect();
    }

    protected function tearDown(): void
    {
        // The rows roll back with the transaction, the cache of ConfigQuery does not.
        ConfigQuery::write('ai_enabled', '1');
        ConfigQuery::write('ai_eu_only', '0');
        ConfigQuery::write('ai_monthly_cap', '0');

        parent::tearDown();
    }

    /**
     * Assigns the model of the tests to the tier of every probe usage, on a connection of the given jurisdiction.
     */
    protected function connect(string $jurisdiction = 'eu', bool $tools = true, string $code = 'probe-main'): void
    {
        AiTierAssignmentQuery::create()->deleteAll();
        AiConnectionQuery::create()->deleteAll();

        $this->connection = (new AiConnection())
            ->setCode($code)->setTitle($code)->setProvider('openai_compatible')->setJurisdiction($jurisdiction)
            ->setBaseUrl(self::PUBLIC_URL)->setActive(true)
            ->setApiKeyEncrypted($this->vault->encrypt('sk-probe-0123456789abcdef'));
        $this->connection->save();

        $this->model = (new AiModel())
            ->setProvider('openai_compatible')->setName('probe-model')->setConnectionId($this->connection->getId())
            ->setInputPrice('1.000000')->setOutputPrice('2.000000')->setSupportsStructuredOutput(true)->setSupportsTools($tools)->setSupportsStreaming(true)
            ->setActive(true)->setSource(AiModelSpecification::SOURCE_MANUAL);
        $this->model->save();

        (new AiTierAssignment())->setTier(AiTier::Fast->value)->setConnectionId($this->connection->getId())->setModelId($this->model->getId())->save();
    }

    /**
     * Builds the gateway over a transport that answers with these responses, in turn.
     *
     * @param list<MockResponse> $responses
     */
    protected function gatewayAnswering(array $responses, int $retryDelayMicroseconds = 0): AiGateway
    {
        $queue = $responses;
        $transport = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $body = isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null;
            $this->sent[] = ['method' => $method, 'url' => $url, 'body' => $body, 'held' => $this->heldAmount()];

            return array_shift($queue) ?? throw new \LogicException('No more mock responses queued.');
        });

        $adapter = new OpenAiCompatibleAdapter(
            (new AiHttpClientFactory(60, 300))->withTransport($transport),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiProviderPresets(),
            new AiMessageBagFactory(new UntrustedContentDelimiter()),
            new AiResultReader(),
            new AiProviderErrorTranslator(),
        );

        $settings = new AiUsageSettingRepository();
        $globalSettings = new AiSettings();
        $probe = 'AiProbeModule\\ProbeGateway';

        if (!class_exists($probe)) {
            throw new \LogicException('The fixture of the probe usages is loaded by setUpBeforeClass().');
        }

        $registry = new AiUsageRegistry(
            ['aiprobe.pages' => $probe, 'aiprobe.assistant' => $probe, 'aiprobe.scoring' => $probe, 'aiprobe.drafts' => $probe, 'aiprobe.live_pages' => $probe], // @phpstan-ignore argument.type (the fixture class is not known to the analysis)
            $settings,
            $globalSettings,
        );
        $calculator = new AiCostCalculator();
        $writer = new AiUsageLogWriter($this->ledger);
        $guardrails = new AiGuardrailRunner($this->installedGuardrails);

        $admission = new AiAdmission(
            $registry,
            $settings,
            $globalSettings,
            new AiTargetResolver($registry, $settings, new AiRoutingRepository(), new AiModelCatalog(), $this->vault),
            $this->resolver,
            $this->hasher,
            new AiRequestMeasure(),
            new AiRateLimiter(new ArrayAdapter()),
            $guardrails,
            $calculator,
            $this->ledger,
            $writer,
        );

        return $this->gateway = new AiGateway($admission, new AiProviderAdapters([$adapter]), $writer, $this->ledger, $calculator, $guardrails, new AiCoreOutputGuardrails(new AiSchemaValidator(), new AiHtmlSanitizer()), new AiPromptSnapshot(), $retryDelayMicroseconds);
    }

    protected function setting(string $usageId, ?string $budget = null, mixed ...$fields): AiUsageSetting
    {
        $setting = AiUsageSettingQuery::create()->findOneByUsageId($usageId) ?? (new AiUsageSetting())->setUsageId($usageId);
        $setting->setMonthlyBudget($budget);

        foreach ($fields as $name => $value) {
            $setting->{'set'.ucfirst($name)}($value);
        }

        $setting->save();

        return $setting;
    }

    protected function request(string $usageId = 'aiprobe.pages', string $prompt = 'Hello', mixed ...$arguments): AiRequest
    {
        return new AiRequest($usageId, [AiMessage::user($prompt)], ...$arguments + ['system' => 'You write for a shop.', 'actor' => AiActor::admin(7)]);
    }

    protected function lastLog(): AiUsageLog
    {
        return AiUsageLogQuery::create()->orderById(Criteria::DESC)->findOne() ?? self::fail('Nothing was logged.');
    }

    /**
     * Cost already in the log this month, as if earlier calls had run.
     */
    protected function spend(string $usageId, string $cost): void
    {
        (new AiUsageLog())
            ->setUsageId($usageId)->setModule(explode('.', $usageId)[0])->setKind('completion')
            ->setCost($cost)->setActorType('system')->setActorHash(str_repeat('a', 64))->setStatus('succeeded')
            ->setCreatedAt(new \DateTimeImmutable('first day of this month 00:00:01'))
            ->save();
    }

    /**
     * @phpstan-impure
     */
    protected function holds(): int
    {
        return AiBudgetReservationQuery::create()->count();
    }

    /**
     * @phpstan-impure
     */
    protected function logCount(): int
    {
        return AiUsageLogQuery::create()->count();
    }

    private function heldAmount(): string
    {
        $statement = Propel::getConnection('TheliaMain')->prepare('SELECT COALESCE(SUM(`amount`), 0) FROM `ai_budget_reservation`');
        $statement->execute();

        return (string) $statement->fetchColumn();
    }
}

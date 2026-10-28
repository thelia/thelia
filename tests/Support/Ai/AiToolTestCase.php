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

use AiProbeModule\ProbeBigTool;
use AiProbeModule\ProbeCatalogTool;
use AiProbeModule\ProbeCustomerTool;
use AiProbeModule\ProbeHiddenTool;
use AiProbeModule\ProbeModuleRightTool;
use AiProbeModule\ProbeNotifyTool;
use AiProbeModule\ProbeReadTool;
use AiProbeModule\ProbeReviewsTool;
use AiProbeModule\ProbeToolState;
use AiProbeModule\ProbeToolUsages;
use AiProbeModule\ProbeWriteTool;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Tool\Log\AiToolCallLogWriter;
use Thelia\Domain\Ai\Tool\Service\AiToolAccessChecker;
use Thelia\Domain\Ai\Tool\Service\AiToolApprovalRepository;
use Thelia\Domain\Ai\Tool\Service\AiToolApprovalService;
use Thelia\Domain\Ai\Tool\Service\AiToolArgumentValidator;
use Thelia\Domain\Ai\Tool\Service\AiToolDeclarations;
use Thelia\Domain\Ai\Tool\Service\AiToolExecutionGuard;
use Thelia\Domain\Ai\Tool\Service\AiToolInstances;
use Thelia\Domain\Ai\Tool\Service\AiToolRateLimiter;
use Thelia\Domain\Ai\Tool\Service\AiToolRegistry;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Builds the registry and the approval service by hand around the probe tools of the fixtures, with a request
 * stack the test controls (no request: a command; a request with a session: the back office).
 */
abstract class AiToolTestCase extends IntegrationTestCase
{
    protected const string SECRET = 'tool-test-secret';

    protected const array TOOLS = [
        'aiprobemodule_read' => ProbeReadTool::class,
        'aiprobemodule_reviews' => ProbeReviewsTool::class,
        'aiprobemodule_notify' => ProbeNotifyTool::class,
        'aiprobemodule_catalog' => ProbeCatalogTool::class,
        'aiprobemodule_orders' => ProbeCustomerTool::class,
        'aiprobemodule_modright' => ProbeModuleRightTool::class,
        'aiprobemodule_hidden' => ProbeHiddenTool::class,
        'aiprobemodule_big' => ProbeBigTool::class,
        'aiprobemodule_update' => ProbeWriteTool::class,
    ];

    protected AiToolRegistry $registry;

    protected AiToolApprovalService $approvals;

    protected AiToolApprovalRepository $approvalRows;

    protected AiToolExecutionGuard $guard;

    protected AiToolAccessChecker $access;

    protected RequestStack $requests;

    protected AiActorHasher $hasher;

    protected RecordingToolLogger $logger;

    public static function setUpBeforeClass(): void
    {
        $fixtures = \dirname(__DIR__, 2).'/fixtures/ai/ProbeModule';

        foreach (['ProbeToolUsages', 'ProbeToolState', 'ProbeTools', 'ProbeBadTools'] as $file) {
            require_once $fixtures.'/'.$file.'.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        ProbeToolState::reset();
        $this->requests = new RequestStack();
        $this->buildServices(self::TOOLS);
    }

    protected function tearDown(): void
    {
        ProbeToolState::reset();
        ConfigQuery::write('ai_enabled', '1');
        ConfigQuery::write('ai_mcp_enabled', '0');
        ConfigQuery::write('ai_tool_approval_ttl_hours', '24');

        parent::tearDown();
    }

    /**
     * @param array<string, class-string> $tools
     */
    protected function buildServices(array $tools): void
    {
        $factories = [];

        foreach ($tools as $name => $class) {
            $factories[$name] = static fn (): object => self::getContainer()->has($class) ? self::getContainer()->get($class) : new $class();
        }

        $this->hasher = new AiActorHasher(self::SECRET);
        $this->guard = new AiToolExecutionGuard();
        $this->access = new AiToolAccessChecker(new SecurityContext($this->requests));
        $this->approvalRows = new AiToolApprovalRepository();
        $this->logger = new RecordingToolLogger();
        $declarations = new AiToolDeclarations($tools);
        $instances = new AiToolInstances(new ServiceLocator($factories));
        $validator = new AiToolArgumentValidator(new AiSchemaValidator());
        $settings = new AiSettings();

        $this->registry = new AiToolRegistry(
            $declarations,
            $instances,
            new AiUsageRegistry(
                ['aiprobe.tools_admin' => ProbeToolUsages::class, 'aiprobe.tools_customer' => ProbeToolUsages::class, 'aiprobe.tools_public' => ProbeToolUsages::class, 'aiprobe.tools_system' => ProbeToolUsages::class], // @phpstan-ignore argument.type (the fixture class is not known to the analysis)
                new AiUsageSettingRepository(),
                $settings,
            ),
            $this->access,
            $validator,
            new AiToolRateLimiter(new ArrayAdapter(), $this->hasher, self::SECRET),
            $this->guard,
            new AiToolCallLogWriter($this->hasher, $this->access),
            $this->hasher,
            $settings,
            $this->logger,
        );
        $this->approvals = new AiToolApprovalService($this->approvalRows, $declarations, $instances, $this->access, $validator, $this->guard, new SecurityContext($this->requests), $this->requests, $this->logger);
    }

    /**
     * Puts a request of the back office on the stack, with this administrator in its session.
     */
    protected function logInAsAdmin(Admin $admin): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->setAdminUser($admin);
        $request = TheliaRequest::create('/admin/ai-tools', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $request->setSession($session);
        $this->requests->push($request);
    }

    protected function logOut(): void
    {
        while (null !== $this->requests->pop()) {
        }
    }

    protected function plainRequest(): Request
    {
        return Request::create('/');
    }
}

/**
 * @internal
 */
final class RecordingToolLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

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

namespace Thelia\Tests\Api\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AiConnection;
use Thelia\Model\AiUsageLog;
use Thelia\Model\AiUsageLogQuery;
use Thelia\Model\AiUsageSettingQuery;
use Thelia\Test\ApiTestCase;

final class AiUsageApiTest extends ApiTestCase
{
    private const SETTINGS = '/api/admin/ai/usage-settings';
    private const USAGES = '/api/admin/ai/usages';
    private const CONSUMPTION = '/api/admin/ai/consumption';

    /** The one usage the core declares itself, so the real register has it. */
    private const USAGE = 'core.guardrail_topic';

    public function testTheRegisterListsTheDeclaredUsages(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', self::USAGES, token: $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $members = json_decode((string) $response->getContent(), true)['hydra:member'];
        $ids = array_column($members, 'id');
        self::assertContains(self::USAGE, $ids);
        $usage = $members[array_search(self::USAGE, $ids, true)];
        self::assertSame('core', $usage['module']);
        self::assertSame(['fast', 'fast'], [$usage['tier'], $usage['declaredTier']]);
        self::assertIsBool($usage['active']);
        self::assertArrayHasKey('complianceDetails', $usage);
    }

    public function testOneUsageIsReadByItsDottedIdentifier(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', self::USAGES.'/'.self::USAGE, token: $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(self::USAGE, json_decode((string) $response->getContent(), true)['id']);
        self::assertSame(404, $this->jsonRequest('GET', self::USAGES.'/nobody.declared', token: $token)->getStatusCode());
    }

    public function testASettingIsCreatedReadUpdatedAndDeleted(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', self::SETTINGS, ['usageId' => self::USAGE, 'monthlyBudget' => '12.500000', 'requestsPerMinute' => 30, 'guardrails' => ['probe_moderation' => 'annotate'], 'storePrompt' => true, 'promptRetentionDays' => 7, 'tier' => 'advanced'], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $data = json_decode((string) $created->getContent(), true);
        self::assertSame('12.500000', $data['monthlyBudget']);
        self::assertSame(['probe_moderation' => 'annotate'], $data['guardrails']);
        self::assertTrue($data['enabled']);
        self::assertTrue($data['transparencyNotice']);
        self::assertSame('advanced', $data['tier']);

        $updated = $this->jsonRequest('PATCH', self::SETTINGS.'/'.$data['id'], ['enabled' => false], $token, 'merge-patch+json');
        self::assertSame(200, $updated->getStatusCode(), (string) $updated->getContent());
        $stored = AiUsageSettingQuery::create()->findPk($data['id']);
        self::assertFalse($stored->getEnabled());
        self::assertSame('12.500000', $stored->getMonthlyBudget());
        self::assertSame(30, $stored->getRequestsPerMinute());
        self::assertSame('advanced', $stored->getTier());
        self::assertSame(['probe_moderation' => 'annotate'], $stored->getGuardrailMap());

        self::assertSame(204, $this->jsonRequest('DELETE', self::SETTINGS.'/'.$data['id'], token: $token)->getStatusCode());
        self::assertNull(AiUsageSettingQuery::create()->findPk($data['id']));
    }

    public function testTheInputIsChecked(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = (new AiConnection())->setCode('pin')->setTitle('pin')->setProvider('mistral')->setJurisdiction('eu');
        $connection->save();

        foreach ([
            ['usageId' => 'nobody.declared'],
            ['monthlyBudget' => 'a lot'],
            ['tier' => 'turbo'],
            ['currency' => 'dollars'],
            ['requestsPerDay' => 0],
            ['pinnedConnectionId' => 999999],
            ['pinnedModelId' => 999999],
            ['guardrails' => ['probe_moderation' => 'ignore']],
            ['guardrails' => ['bad name' => 'block']],
        ] as $override) {
            $response = $this->jsonRequest('POST', self::SETTINGS, array_merge(['usageId' => self::USAGE], $override), $token);

            self::assertSame(422, $response->getStatusCode(), json_encode($override).' '.$response->getContent());
        }

        self::assertSame(0, AiUsageSettingQuery::create()->count());
    }

    public function testAUsageHasOneSetting(): void
    {
        $token = $this->authenticateAsAdmin();
        self::assertSame(201, $this->jsonRequest('POST', self::SETTINGS, ['usageId' => self::USAGE], $token)->getStatusCode());

        self::assertSame(422, $this->jsonRequest('POST', self::SETTINGS, ['usageId' => self::USAGE], $token)->getStatusCode());
    }

    public function testTheConsumptionIsSummedByMonthAndUsage(): void
    {
        $token = $this->authenticateAsAdmin();
        AiUsageLogQuery::create()->deleteAll();
        $this->log('probe.alpha', '0.10000000', '2026-09-10 10:00:00', 'succeeded', 100, 10);
        $this->log('probe.alpha', '0.25000000', '2026-09-20 10:00:00', 'failed', 50, 0);
        $this->log('probe.alpha', '1.00000000', '2026-10-02 10:00:00', 'succeeded', 10, 5);
        $this->log('probe.beta', '0.50000000', '2026-09-11 10:00:00', 'succeeded', 1, 1, 'external');

        $all = json_decode((string) $this->jsonRequest('GET', self::CONSUMPTION, token: $token)->getContent(), true)['hydra:member'];
        self::assertSame(['2026-10/probe.alpha', '2026-09/probe.alpha', '2026-09/probe.beta'], array_column($all, 'id'));

        $september = json_decode((string) $this->jsonRequest('GET', self::CONSUMPTION.'?month=2026-09&usageId=probe.alpha', token: $token)->getContent(), true)['hydra:member'];
        self::assertCount(1, $september);
        self::assertSame('0.35000000', $september[0]['cost']);
        self::assertSame(2, $september[0]['calls']);
        self::assertSame(1, $september[0]['failures']);
        self::assertSame(150, $september[0]['inputTokens']);
        self::assertSame('USD', $september[0]['currency']);

        $external = json_decode((string) $this->jsonRequest('GET', self::CONSUMPTION.'?module=probe&from=2026-09&to=2026-09', token: $token)->getContent(), true)['hydra:member'];
        $beta = $external[array_search('probe.beta', array_column($external, 'usageId'), true)];
        self::assertSame(0, $beta['calls']);
        self::assertSame(1, $beta['externalCalls']);
        self::assertSame('0.50000000', $beta['cost']);

        self::assertSame(422, $this->jsonRequest('GET', self::CONSUMPTION.'?month=september', token: $token)->getStatusCode());
    }

    public function testTheConsumptionCostIsAnExactDecimal(): void
    {
        $token = $this->authenticateAsAdmin();
        AiUsageLogQuery::create()->deleteAll();
        // A float reads this amount back as 88888888.88888890.
        $this->log('probe.exact', '88888888.88888889', '2026-09-10 10:00:00', 'succeeded', 1, 1);

        $members = json_decode((string) $this->jsonRequest('GET', self::CONSUMPTION.'?month=2026-09', token: $token)->getContent(), true)['hydra:member'];

        self::assertSame('88888888.88888889', $members[0]['cost']);
    }

    public function testNoTokenIsRefused(): void
    {
        foreach ([['GET', self::SETTINGS], ['POST', self::SETTINGS], ['DELETE', self::SETTINGS.'/1'], ['GET', self::USAGES], ['GET', self::USAGES.'/'.self::USAGE], ['GET', self::CONSUMPTION]] as [$method, $uri]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : ['usageId' => self::USAGE])->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testAnAdminWithoutTheAiRightIsRefused(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($admin);

        foreach ([['GET', self::SETTINGS], ['POST', self::SETTINGS], ['GET', self::USAGES], ['GET', self::USAGES.'/'.self::USAGE], ['GET', self::CONSUMPTION]] as [$method, $uri]) {
            self::assertSame(403, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : ['usageId' => self::USAGE], $token)->getStatusCode(), $method.' '.$uri);
        }
    }

    private function log(string $usageId, string $cost, string $at, string $status, int $input, int $output, string $kind = 'completion'): void
    {
        (new AiUsageLog())
            ->setUsageId($usageId)->setModule(explode('.', $usageId)[0])->setKind($kind)
            ->setInputTokens($input)->setOutputTokens($output)
            ->setCost($cost)->setActorType('system')->setActorHash(str_repeat('a', 64))->setStatus($status)
            ->setCreatedAt(new \DateTimeImmutable($at))
            ->save();
    }
}

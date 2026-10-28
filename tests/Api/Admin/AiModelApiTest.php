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
use Thelia\Model\AiModelQuery;
use Thelia\Model\AiTierAssignmentQuery;
use Thelia\Model\AiUsageSetting;
use Thelia\Test\ApiTestCase;

final class AiModelApiTest extends ApiTestCase
{
    private const MODELS = '/api/admin/ai/models';
    private const TIERS = '/api/admin/ai/tiers';

    public function testPostCreatesAManualModelWithItsPrices(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', self::MODELS, $this->model(['inputPrice' => '0.150000', 'supportsTools' => true]), $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('manual', $data['source']);
        self::assertSame('0.150000', $data['inputPrice']);
        self::assertTrue($data['supportsTools']);
        self::assertSame('USD', $data['currency']);
    }

    public function testTheNameOfAProviderModelMayCarryItsOwnPunctuation(): void
    {
        $token = $this->authenticateAsAdmin();

        foreach (['llama3.1:8b', 'mistralai/mistral-small-2603'] as $name) {
            self::assertSame(201, $this->jsonRequest('POST', self::MODELS, $this->model(['name' => $name]), $token)->getStatusCode(), $name);
        }
    }

    public function testAProviderLevelModelIsUniqueDespiteTheNullConnection(): void
    {
        $token = $this->authenticateAsAdmin();
        self::assertSame(201, $this->jsonRequest('POST', self::MODELS, $this->model(), $token)->getStatusCode());

        $response = $this->jsonRequest('POST', self::MODELS, $this->model(), $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, AiModelQuery::create()->filterByName('probe-model')->count());
    }

    public function testTheSameNameIsAllowedOnAConnectionAndOnAnother(): void
    {
        $token = $this->authenticateAsAdmin();
        $first = $this->connection('first', 'ollama');
        $second = $this->connection('second', 'ollama');

        foreach ([$first, $second] as $connection) {
            $response = $this->jsonRequest('POST', self::MODELS, $this->model(['provider' => 'ollama', 'connectionId' => $connection->getId()]), $token);
            self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        }

        $again = $this->jsonRequest('POST', self::MODELS, $this->model(['provider' => 'ollama', 'connectionId' => $first->getId()]), $token);
        self::assertSame(422, $again->getStatusCode());
    }

    public function testTheInputIsChecked(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-one', 'mistral');

        foreach ([
            ['provider' => 'nope'],
            ['inputPrice' => 'cheap'],
            ['inputPrice' => '-1'],
            ['currency' => 'dollars'],
            ['connectionId' => 999999],
            ['provider' => 'openai', 'connectionId' => $connection->getId()],
            ['contextWindow' => 0],
            ['name' => 'bad name'],
        ] as $override) {
            $response = $this->jsonRequest('POST', self::MODELS, $this->model($override), $token);

            self::assertSame(422, $response->getStatusCode(), json_encode($override).' '.$response->getContent());
        }

        self::assertSame(0, AiModelQuery::create()->filterByName('probe-model')->count());
    }

    public function testAnUpdateKeepsWhatItDoesNotCarry(): void
    {
        $token = $this->authenticateAsAdmin();
        $id = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(['inputPrice' => '2.500000', 'title' => 'Probe']), $token)->getContent(), true)['id'];

        $response = $this->jsonRequest('PATCH', self::MODELS.'/'.$id, ['outputPrice' => '7.000000'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $stored = AiModelQuery::create()->findPk($id);
        self::assertSame('2.500000', $stored->getInputPrice());
        self::assertSame('7.000000', $stored->getOutputPrice());
        self::assertSame('Probe', $stored->getTitle());
    }

    public function testATierTakesAModelOfItsConnectionOnly(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-tier');
        $modelId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(), $token)->getContent(), true)['id'];
        $foreignId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(['provider' => 'openai', 'name' => 'gpt-probe']), $token)->getContent(), true)['id'];

        $wrong = $this->jsonRequest('POST', self::TIERS, ['tier' => 'fast', 'connectionId' => $connection->getId(), 'modelId' => $foreignId], $token);
        self::assertSame(422, $wrong->getStatusCode(), (string) $wrong->getContent());

        $ok = $this->jsonRequest('POST', self::TIERS, ['tier' => 'fast', 'connectionId' => $connection->getId(), 'modelId' => $modelId], $token);
        self::assertSame(201, $ok->getStatusCode(), (string) $ok->getContent());

        $duplicate = $this->jsonRequest('POST', self::TIERS, ['tier' => 'fast', 'connectionId' => $connection->getId(), 'modelId' => $modelId], $token);
        self::assertSame(422, $duplicate->getStatusCode());

        self::assertSame(422, $this->jsonRequest('POST', self::TIERS, ['tier' => 'huge', 'connectionId' => $connection->getId(), 'modelId' => $modelId], $token)->getStatusCode());
    }

    public function testAnAssignmentCanBeMovedAndRemoved(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-move');
        $first = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(), $token)->getContent(), true)['id'];
        $second = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(['name' => 'probe-two']), $token)->getContent(), true)['id'];
        $id = json_decode((string) $this->jsonRequest('POST', self::TIERS, ['tier' => 'standard', 'connectionId' => $connection->getId(), 'modelId' => $first], $token)->getContent(), true)['id'];

        $moved = $this->jsonRequest('PATCH', self::TIERS.'/'.$id, ['modelId' => $second], $token, 'merge-patch+json');
        self::assertSame(200, $moved->getStatusCode(), (string) $moved->getContent());
        self::assertSame($second, AiTierAssignmentQuery::create()->findPk($id)->getModelId());

        self::assertSame(204, $this->jsonRequest('DELETE', self::TIERS.'/'.$id, token: $token)->getStatusCode());
        self::assertNull(AiTierAssignmentQuery::create()->findPk($id));
    }

    public function testAnAssignedModelIsNotDeleted(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-keep');
        $modelId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(), $token)->getContent(), true)['id'];
        self::assertSame(201, $this->jsonRequest('POST', self::TIERS, ['tier' => 'advanced', 'connectionId' => $connection->getId(), 'modelId' => $modelId], $token)->getStatusCode());

        self::assertSame(409, $this->jsonRequest('DELETE', self::MODELS.'/'.$modelId, token: $token)->getStatusCode());
        self::assertNotNull(AiModelQuery::create()->findPk($modelId));
    }

    public function testAnAssignedModelKeepsTheProviderAndTheConnectionItsTierReachesItBy(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-tier');
        $other = $this->connection('mistral-other');
        $modelId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(), $token)->getContent(), true)['id'];
        self::assertSame(201, $this->jsonRequest('POST', self::TIERS, ['tier' => 'advanced', 'connectionId' => $connection->getId(), 'modelId' => $modelId], $token)->getStatusCode());

        $provider = $this->jsonRequest('PATCH', self::MODELS.'/'.$modelId, ['provider' => 'anthropic'], $token, 'merge-patch+json');
        $bound = $this->jsonRequest('PATCH', self::MODELS.'/'.$modelId, ['connectionId' => $other->getId()], $token, 'merge-patch+json');

        self::assertSame(422, $provider->getStatusCode(), (string) $provider->getContent());
        self::assertSame(422, $bound->getStatusCode(), (string) $bound->getContent());
        self::assertSame('mistral', AiModelQuery::create()->findPk($modelId)->getProvider());
        self::assertNull(AiModelQuery::create()->findPk($modelId)->getConnectionId());
        self::assertSame(200, $this->jsonRequest('PATCH', self::MODELS.'/'.$modelId, ['connectionId' => $connection->getId()], $token, 'merge-patch+json')->getStatusCode());
    }

    public function testAModelAUsageIsPinnedToKeepsTheProviderOfTheConnectionOfItsTier(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('mistral-tier');
        $other = $this->connection('mistral-other');
        $tierModelId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(['name' => 'tier-model']), $token)->getContent(), true)['id'];
        self::assertSame(201, $this->jsonRequest('POST', self::TIERS, ['tier' => 'advanced', 'connectionId' => $connection->getId(), 'modelId' => $tierModelId], $token)->getStatusCode());

        $pinnedModelId = json_decode((string) $this->jsonRequest('POST', self::MODELS, $this->model(['name' => 'pinned-model']), $token)->getContent(), true)['id'];
        (new AiUsageSetting())->setUsageId('test.pinned_model')->setTier('advanced')->setPinnedModelId($pinnedModelId)->save();

        $provider = $this->jsonRequest('PATCH', self::MODELS.'/'.$pinnedModelId, ['provider' => 'anthropic'], $token, 'merge-patch+json');

        self::assertSame(422, $provider->getStatusCode(), (string) $provider->getContent());
        self::assertSame('mistral', AiModelQuery::create()->findPk($pinnedModelId)->getProvider());
        self::assertSame(200, $this->jsonRequest('PATCH', self::MODELS.'/'.$pinnedModelId, ['connectionId' => $other->getId()], $token, 'merge-patch+json')->getStatusCode(), 'bound to a connection that serves it, the usage reaches it there');
    }

    public function testNoTokenIsRefused(): void
    {
        foreach ([['GET', self::MODELS], ['POST', self::MODELS], ['GET', self::TIERS], ['POST', self::TIERS], ['DELETE', self::MODELS.'/1'], ['DELETE', self::TIERS.'/1']] as [$method, $uri]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : ['name' => 'x'])->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testAnAdminWithoutTheAiRightIsRefused(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($admin);

        foreach ([['GET', self::MODELS], ['POST', self::MODELS], ['GET', self::TIERS], ['POST', self::TIERS]] as [$method, $uri]) {
            self::assertSame(403, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : $this->model(), $token)->getStatusCode(), $method.' '.$uri);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function model(array $overrides = []): array
    {
        return array_merge(['provider' => 'mistral', 'name' => 'probe-model'], $overrides);
    }

    private function connection(string $code, string $provider = 'mistral'): AiConnection
    {
        $connection = (new AiConnection())->setCode($code)->setTitle($code)->setProvider($provider)->setJurisdiction('eu');
        $connection->save();

        return $connection;
    }
}

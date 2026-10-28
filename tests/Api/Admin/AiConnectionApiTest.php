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
use Thelia\Domain\Ai\AiTier;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Model\AiModel;
use Thelia\Model\AiTierAssignment;
use Thelia\Test\ApiTestCase;

final class AiConnectionApiTest extends ApiTestCase
{
    private const URI = '/api/admin/ai/connections';
    private const KEY = 'sk-api-test-0123456789abcdef';

    public function testPostCreatesAConnectionAndNeverReturnsTheKey(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', self::URI, $this->body(), $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('mistral-main', $data['code']);
        self::assertSame('eu', $data['jurisdiction']);
        self::assertTrue($data['hasKey']);
        self::assertArrayNotHasKey('apiKey', $data);
        self::assertArrayNotHasKey('apiKeyEncrypted', $data);
        self::assertStringNotContainsString(self::KEY, (string) $response->getContent());

        $stored = AiConnectionQuery::create()->findOneByCode('mistral-main');
        self::assertNotNull($stored->getApiKeyEncrypted());
        self::assertStringNotContainsString(self::KEY, $stored->getApiKeyEncrypted());
    }

    public function testNoReadCarriesTheKey(): void
    {
        $token = $this->authenticateAsAdmin();
        $id = json_decode((string) $this->jsonRequest('POST', self::URI, $this->body(), $token)->getContent(), true)['id'];

        foreach ([self::URI, self::URI.'/'.$id] as $uri) {
            $response = $this->jsonRequest('GET', $uri, token: $token);

            self::assertSame(200, $response->getStatusCode(), $uri);
            self::assertStringNotContainsString(self::KEY, (string) $response->getContent(), $uri);
            self::assertStringNotContainsString('apiKeyEncrypted', (string) $response->getContent(), $uri);
            self::assertStringContainsString('"hasKey":true', (string) $response->getContent(), $uri);
        }
    }

    public function testAnUpdateWithoutKeyKeepsTheStoredKey(): void
    {
        $token = $this->authenticateAsAdmin();
        $id = json_decode((string) $this->jsonRequest('POST', self::URI, $this->body(), $token)->getContent(), true)['id'];
        $encrypted = AiConnectionQuery::create()->findPk($id)->getApiKeyEncrypted();

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$id, ['title' => 'Renamed'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $stored = AiConnectionQuery::create()->findPk($id);
        self::assertSame('Renamed', $stored->getTitle());
        self::assertSame($encrypted, $stored->getApiKeyEncrypted());
    }

    public function testAnUpdateWithAKeyReplacesIt(): void
    {
        $token = $this->authenticateAsAdmin();
        $id = json_decode((string) $this->jsonRequest('POST', self::URI, $this->body(), $token)->getContent(), true)['id'];
        $encrypted = AiConnectionQuery::create()->findPk($id)->getApiKeyEncrypted();

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$id, ['apiKey' => 'sk-another-key-0123456789'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotSame($encrypted, AiConnectionQuery::create()->findPk($id)->getApiKeyEncrypted());
    }

    public function testACompatibleConnectionNeedsItsJurisdictionDeclared(): void
    {
        $token = $this->authenticateAsAdmin();

        $refused = $this->jsonRequest('POST', self::URI, $this->body(['code' => 'own', 'provider' => 'openai_compatible', 'baseUrl' => 'https://93.184.216.34/v1']), $token);
        self::assertSame(422, $refused->getStatusCode(), (string) $refused->getContent());

        $accepted = $this->jsonRequest('POST', self::URI, $this->body(['code' => 'own', 'provider' => 'openai_compatible', 'baseUrl' => 'https://93.184.216.34/v1', 'jurisdiction' => 'non_eu']), $token);
        self::assertSame(201, $accepted->getStatusCode(), (string) $accepted->getContent());
        self::assertSame('non_eu', json_decode((string) $accepted->getContent(), true)['jurisdiction']);
    }

    public function testTheBaseUrlIsChecked(): void
    {
        $token = $this->authenticateAsAdmin();

        foreach (['http://93.184.216.34', 'https://127.0.0.1', 'https://user:pass@93.184.216.34'] as $url) {
            $response = $this->jsonRequest('POST', self::URI, $this->body(['baseUrl' => $url]), $token);

            self::assertSame(422, $response->getStatusCode(), $url.' '.$response->getContent());
        }

        self::assertSame(0, AiConnectionQuery::create()->count());
    }

    public function testAPrivateAddressNeedsTheExplicitAllowance(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', self::URI, $this->body(['code' => 'lan', 'provider' => 'ollama', 'baseUrl' => 'http://192.168.1.20:11434', 'jurisdiction' => 'eu', 'allowPrivateNetwork' => true, 'apiKey' => null]), $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheCodeAndTheProviderAreChecked(): void
    {
        $token = $this->authenticateAsAdmin();
        self::assertSame(201, $this->jsonRequest('POST', self::URI, $this->body(), $token)->getStatusCode());

        self::assertSame(422, $this->jsonRequest('POST', self::URI, $this->body(), $token)->getStatusCode(), 'duplicate code');
        self::assertSame(422, $this->jsonRequest('POST', self::URI, $this->body(['code' => 'Bad Code']), $token)->getStatusCode());
        self::assertSame(422, $this->jsonRequest('POST', self::URI, $this->body(['code' => 'other', 'provider' => 'nope']), $token)->getStatusCode());
    }

    public function testADeletedConnectionIsGone(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('gone');

        self::assertSame(204, $this->jsonRequest('DELETE', self::URI.'/'.$connection->getId(), token: $token)->getStatusCode());
        self::assertNull(AiConnectionQuery::create()->findPk($connection->getId()));
        self::assertSame(404, $this->jsonRequest('DELETE', self::URI.'/'.$connection->getId(), token: $token)->getStatusCode());
    }

    public function testAConnectionAssignedToATierIsNotDeleted(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('assigned');
        $model = (new AiModel())->setProvider('mistral')->setName('probe-model')->setConnectionId($connection->getId());
        $model->save();
        (new AiTierAssignment())->setTier(AiTier::Fast->value)->setConnectionId($connection->getId())->setModelId($model->getId())->save();

        $response = $this->jsonRequest('DELETE', self::URI.'/'.$connection->getId(), token: $token);

        self::assertSame(409, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull(AiConnectionQuery::create()->findPk($connection->getId()));
    }

    public function testTheTestOfAConnectionWithoutModelSaysSoAndKeepsTheKeyOut(): void
    {
        $token = $this->authenticateAsAdmin();
        $connection = $this->connection('empty', 'openai_compatible');

        $response = $this->jsonRequest('POST', self::URI.'/'.$connection->getId().'/test', [], $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertStringContainsString('No model', $data['checkError']);
        self::assertNotNull($data['checkedAt']);
        self::assertStringNotContainsString(self::KEY, (string) $response->getContent());
        self::assertSame(404, $this->jsonRequest('POST', self::URI.'/999999/test', [], $token)->getStatusCode());
    }

    public function testNoTokenIsRefused(): void
    {
        $connection = $this->connection('anon');

        foreach ([['GET', self::URI], ['GET', self::URI.'/'.$connection->getId()], ['POST', self::URI], ['PATCH', self::URI.'/'.$connection->getId()], ['DELETE', self::URI.'/'.$connection->getId()], ['POST', self::URI.'/'.$connection->getId().'/test']] as [$method, $uri]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : ['title' => 'x'], null, 'PATCH' === $method ? 'merge-patch+json' : 'jsonld')->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testAnAdminWithoutTheAiRightIsRefused(): void
    {
        $connection = $this->connection('forbidden');
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]]);
        $token = $this->authenticateAsAdmin($admin);

        foreach ([['GET', self::URI], ['GET', self::URI.'/'.$connection->getId()], ['POST', self::URI], ['DELETE', self::URI.'/'.$connection->getId()], ['POST', self::URI.'/'.$connection->getId().'/test']] as [$method, $uri]) {
            self::assertSame(403, $this->jsonRequest($method, $uri, 'GET' === $method ? [] : $this->body(), $token)->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testAReadOnlyAiProfileCannotWrite(): void
    {
        $connection = $this->connection('readonly');
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::AI => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertSame(200, $this->jsonRequest('GET', self::URI.'/'.$connection->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', self::URI.'/'.$connection->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', self::URI.'/'.$connection->getId().'/test', [], $token)->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return array_merge(['code' => 'mistral-main', 'title' => 'Mistral', 'provider' => 'mistral', 'apiKey' => self::KEY], $overrides);
    }

    private function connection(string $code, string $provider = 'mistral'): AiConnection
    {
        $connection = (new AiConnection())->setCode($code)->setTitle($code)->setProvider($provider)->setJurisdiction('eu');
        $connection->save();

        return $connection;
    }
}

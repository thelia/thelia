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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenRateLimiter;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenService;
use Thelia\Model\Admin;
use Thelia\Model\AiToolTokenQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProfileResourceQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Kernel\McpProbeKernel;

/**
 * What the firewall of `^/api/mcp` lets through: a dedicated token, an administrator who still exists, a server
 * that is on, and rights that are those of the administrator now, reduced to the scopes of the token.
 */
final class AiMcpAuthenticationTest extends ApiTestCase
{
    private Admin $admin;

    protected static function getKernelClass(): string
    {
        return McpProbeKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('ai_enabled', '1');
        ConfigQuery::write('ai_mcp_enabled', '1');
        $this->admin = $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::AI_MCP => [AccessManager::CREATE],
            AdminResources::PRODUCT => [AccessManager::VIEW],
        ], ['login' => 'marie']);
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('ai_mcp_enabled', '0');

        parent::tearDown();
    }

    public function testAValidTokenReachesTheEndpointAsItsAdministrator(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);

        $response = $this->probe($token->token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame('marie', $body['login']);
        self::assertSame($token->id, $body['tokenId']);
        self::assertStringNotContainsString($token->token, (string) $response->getContent());
    }

    public function testTheBearerSchemeIsReadWhateverItsCase(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);

        foreach (['bearer', 'BEARER', 'bEaReR'] as $scheme) {
            self::assertSame(Response::HTTP_OK, $this->probe(null, $scheme.' '.$token->token)->getStatusCode(), $scheme);
        }
    }

    public function testNoHeaderOrAWrongSchemeOrAnUnknownTokenIs401WithoutDetail(): void
    {
        $unknown = 'thelia_mcp_'.str_repeat('Q', 43);

        foreach ([[null, null], ['Basic abc', null], ['Bearer', null], ['Bearer '.$unknown, null], ['Bearer not-a-token', null], ['Bearer', null]] as [$header]) {
            $response = $this->probe(null, $header);

            self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), (string) $header);
            self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
            self::assertSame('{"error":"unauthorized"}', $response->getContent());
        }
    }

    public function testARevokedTokenIs401AtTheNextRequest(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        self::assertSame(Response::HTTP_OK, $this->probe($token->token)->getStatusCode());

        static::getContainer()->get(AiToolTokenService::class)->revoke($token->id, $this->admin->getId());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->probe($token->token)->getStatusCode());
    }

    public function testAnExpiredTokenIs401(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        AiToolTokenQuery::create()->filterById($token->id)->update(['ExpiresAt' => new \DateTimeImmutable('-1 second')]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->probe($token->token)->getStatusCode());
    }

    public function testATokenOfADeletedAdministratorIs401(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        self::assertSame(Response::HTTP_OK, $this->probe($token->token)->getStatusCode());

        $this->admin->delete();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->probe($token->token)->getStatusCode());
    }

    public function testAnAdministratorWhoLostHisRowButNotHisTokenIs401(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        $connection = \Propel\Runtime\Propel::getConnection('TheliaMain');
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $connection->exec('DELETE FROM `admin` WHERE `id` = '.$this->admin->getId());
            self::assertNotNull(AiToolTokenQuery::create()->findPk($token->id));
            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->probe($token->token)->getStatusCode());
        } finally {
            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function testWithTheServerOffEveryRequestIs404(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        ConfigQuery::write('ai_mcp_enabled', '0');

        foreach ([$token->token, null, 'thelia_mcp_'.str_repeat('Q', 43)] as $presented) {
            $response = $this->probe($presented);

            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
            self::assertSame('{"error":"not_found"}', $response->getContent());
        }

        ConfigQuery::write('ai_mcp_enabled', '1');
        ConfigQuery::write('ai_enabled', '0');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->probe($token->token)->getStatusCode(), 'the master switch cuts the MCP server too');
    }

    public function testTheRightsAreTheAclOfTheAdministratorAndTheScopesOfTheToken(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.order', 'access' => 'VIEW']]);

        $covered = $this->decode($this->probe($token->token, null, 'admin.product'));
        self::assertTrue($covered['adminHolds']);
        self::assertTrue($covered['tokenCovers']);

        $notHeld = $this->decode($this->probe($token->token, null, 'admin.order'));
        self::assertFalse($notHeld['adminHolds'], 'the ACL of the administrator is not widened by the token');
        self::assertTrue($notHeld['tokenCovers']);

        $narrow = $this->createToken([['resource' => 'admin.order', 'access' => 'VIEW']]);
        $notCovered = $this->decode($this->probe($narrow->token, null, 'admin.product'));
        self::assertTrue($notCovered['adminHolds']);
        self::assertFalse($notCovered['tokenCovers'], 'the token is not wider than its scopes');

        $wrongAccess = $this->decode($this->probe($token->token, null, 'admin.product', 'UPDATE'));
        self::assertFalse($wrongAccess['adminHolds']);
        self::assertFalse($wrongAccess['tokenCovers']);
    }

    public function testTheAclOfTheAdministratorIsReadAtEachRequest(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        self::assertTrue($this->decode($this->probe($token->token, null, 'admin.product'))['adminHolds']);

        ProfileResourceQuery::create()->filterByProfileId($this->admin->getProfileId())->delete();

        $after = $this->decode($this->probe($token->token, null, 'admin.product'));
        self::assertFalse($after['adminHolds']);
        self::assertTrue($after['tokenCovers']);
    }

    public function testTheTokenOpensNothingButTheMcpEndpoint(): void
    {
        $token = $this->createToken([], true);

        $this->client->request('GET', '/api/admin/products', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token->token, 'HTTP_ACCEPT' => 'application/json']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testTheJwtOfAnAdministratorIsNotAnMcpToken(): void
    {
        $jwt = $this->authenticateAsAdmin($this->createFixtureFactory()->admin(['login' => 'root']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->probe($jwt)->getStatusCode());
    }

    public function testATokenThatExceedsItsRateIs429WithRetryAfter(): void
    {
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        $limiter = static::getContainer()->get(AiToolTokenRateLimiter::class);

        for ($i = 0; $i < AiToolTokenRateLimiter::PER_MINUTE - 1; ++$i) {
            self::assertNull($limiter->consume($token->id));
        }

        self::assertSame(Response::HTTP_OK, $this->probe($token->token)->getStatusCode(), 'the 60th request of the minute passes');

        $response = $this->probe($token->token);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertGreaterThanOrEqual(1, (int) $response->headers->get('Retry-After'));

        $other = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);
        self::assertSame(Response::HTTP_OK, $this->probe($other->token)->getStatusCode(), 'the count is by token');
    }

    public function testAnUnknownTokenDoesNotUseTheRateOfAnyToken(): void
    {
        $limiter = static::getContainer()->get(AiToolTokenRateLimiter::class);
        $token = $this->createToken([['resource' => 'admin.product', 'access' => 'VIEW']]);

        for ($i = 0; $i < 70; ++$i) {
            $this->probe('thelia_mcp_'.str_repeat('Z', 43));
        }

        self::assertSame(Response::HTTP_OK, $this->probe($token->token)->getStatusCode());
        self::assertInstanceOf(AiToolTokenRateLimiter::class, $limiter);
    }

    /**
     * @param list<array<string, string>> $scopes
     */
    private function createToken(array $scopes, bool $allRights = false): \Thelia\Domain\Ai\Tool\AiCreatedToolToken
    {
        return static::getContainer()->get(AiToolTokenService::class)->create($this->admin->getId(), 'test', $scopes, $allRights);
    }

    private function probe(?string $token, ?string $header = null, string $resource = 'admin.product', string $access = 'VIEW'): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];

        if (null !== $header) {
            $server['HTTP_AUTHORIZATION'] = $header;
        } elseif (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request('POST', McpProbeKernel::ROUTE_PATH.'?'.http_build_query(['resource' => $resource, 'access' => $access]), server: $server);

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}

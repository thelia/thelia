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
use Thelia\Domain\Ai\Tool\AiCreatedToolToken;
use Thelia\Domain\Ai\Tool\Mcp\McpRequestGuardListener;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenService;
use Thelia\Model\Admin;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProfileResourceQuery;
use Thelia\Test\ApiTestCase;

/**
 * The MCP server of the core, end to end: real JSON-RPC requests on `/api/mcp` with a token the service created,
 * through the firewall, the SDK of symfony/mcp-bundle and the registry.
 */
final class AiMcpServerTest extends ApiTestCase
{
    private const string PROTOCOL = '2025-11-25';

    private Admin $reader;

    private Admin $editor;

    private ProductSaleElements $pse;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('ai_enabled', '1');
        ConfigQuery::write('ai_mcp_enabled', '1');
        $factory = $this->createFixtureFactory();
        $this->reader = $factory->restrictedAdmin([AdminResources::AI_MCP => [AccessManager::CREATE], AdminResources::PRODUCT => [AccessManager::VIEW]], ['login' => 'marie']);
        $this->editor = $factory->restrictedAdmin([AdminResources::AI_MCP => [AccessManager::CREATE], AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]], ['login' => 'lea']);
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency, ['ref' => 'MUG-THELIA', 'title' => 'Mug Thelia', 'basePrice' => 16.5, 'baseQuantity' => 12]);
        $this->pse = $product->getDefaultSaleElements();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('ai_mcp_enabled', '0');
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testInitializeAnnouncesToolsAndNothingElse(): void
    {
        $token = $this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]);

        $response = $this->rpc($token, 'initialize', ['protocolVersion' => self::PROTOCOL, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']], 1);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        // The SDK leaves `listChanged` out when it is false, which the protocol reads as false.
        self::assertStringContainsString('"capabilities":{"tools":{}}', (string) $response->getContent());
        self::assertNotEmpty($response->headers->get('Mcp-Session-Id'));
    }

    public function testToolsListOffersTheToolsOfTheAdministratorWithinTheScopesOfTheToken(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $tools = $this->decode($this->call($session, 'tools/list', null, 2))['result']['tools'];

        self::assertSame(['thelia_search_products'], array_column($tools, 'name'));
        self::assertSame(['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false], $tools[0]['annotations']);
        self::assertSame('object', $tools[0]['inputSchema']['type']);
        self::assertFalse($tools[0]['inputSchema']['additionalProperties']);
        self::assertStringNotContainsString('thelia_update_product_price', (string) $this->client->getResponse()->getContent());
    }

    public function testAnAdministratorWhoMayEditSeesTheWriteToolButAScopeCanHideIt(): void
    {
        $wide = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.product', 'access' => 'UPDATE']]));
        $tools = $this->decode($this->call($wide, 'tools/list', null, 2))['result']['tools'];
        $byName = array_column($tools, null, 'name');

        self::assertSame(['thelia_search_products', 'thelia_update_product_price'], array_keys($byName));
        self::assertSame(['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false], $byName['thelia_update_product_price']['annotations']);

        $narrow = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW']]));
        self::assertSame(['thelia_search_products'], array_column($this->decode($this->call($narrow, 'tools/list', null, 2))['result']['tools'], 'name'));
    }

    public function testPromptsAndResourcesAreEmpty(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        self::assertSame([], $this->decode($this->call($session, 'prompts/list', null, 2))['result']['prompts']);
        self::assertSame([], $this->decode($this->call($session, 'resources/list', null, 3))['result']['resources']);
        self::assertSame([], $this->decode($this->call($session, 'resources/templates/list', null, 4))['result']['resourceTemplates']);
    }

    public function testAReadToolAnswersWithTheTextAndTheStructuredContent(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $result = $this->decode($this->call($session, 'tools/call', ['name' => 'thelia_search_products', 'arguments' => ['query' => 'mug', 'limit' => 2]], 3))['result'];

        self::assertFalse($result['isError']);
        self::assertSame(1, $result['structuredContent']['count']);
        self::assertSame('MUG-THELIA', $result['structuredContent']['products'][0]['ref']);
        self::assertSame($this->pse->getId(), $result['structuredContent']['products'][0]['variants'][0]['pse_id']);
        self::assertSame('text', $result['content'][0]['type']);
        self::assertSame($result['structuredContent'], json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testInvalidArgumentsAreAnErrorResultThatNamesThePathNotTheValue(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $result = $this->decode($this->call($session, 'tools/call', ['name' => 'thelia_search_products', 'arguments' => ['query' => 'mug', 'limit' => 99]], 3))['result'];

        self::assertTrue($result['isError']);
        self::assertSame('invalid_arguments', $result['structuredContent']['error']);
        self::assertStringContainsString('/limit', $result['structuredContent']['details'][0]);
        self::assertStringNotContainsString('99', (string) json_encode($result['structuredContent']['details']));
    }

    public function testAWriteToolStoresAPendingApprovalAndChangesNothing(): void
    {
        $session = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.product', 'access' => 'UPDATE']]));
        $before = $this->priceOf();

        $result = $this->decode($this->call($session, 'tools/call', ['name' => 'thelia_update_product_price', 'arguments' => ['pse_id' => $this->pse->getId(), 'price' => '9.00']], 4))['result'];

        self::assertFalse($result['isError']);
        self::assertSame('pending_approval', $result['structuredContent']['status']);
        $approval = AiToolApprovalQuery::create()->findPk($result['structuredContent']['approval_id']);
        self::assertNotNull($approval);
        self::assertSame('pending', $approval->getStatus());
        self::assertSame('mcp', $approval->getChannel());
        self::assertSame($this->editor->getId(), $approval->getProposedByAdminId());
        self::assertSame($before, $this->priceOf(), 'nothing is applied from MCP');
    }

    public function testAToolTheTokenDoesNotCoverIsAnUnknownToolForAnyReason(): void
    {
        $session = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $forbidden = $this->decode($this->call($session, 'tools/call', ['name' => 'thelia_update_product_price', 'arguments' => ['pse_id' => $this->pse->getId(), 'price' => '9.00']], 4));
        $missing = $this->decode($this->call($session, 'tools/call', ['name' => 'does_not_exist', 'arguments' => new \stdClass()], 5));

        self::assertSame(-32602, $forbidden['error']['code']);
        self::assertSame('Unknown tool: thelia_update_product_price', $forbidden['error']['message']);
        self::assertSame(-32602, $missing['error']['code']);
        self::assertSame('Unknown tool: does_not_exist', $missing['error']['message']);
        self::assertSame(0, AiToolApprovalQuery::create()->count());
    }

    public function testANameThatIsNoToolNameIsAnUnknownTool(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $response = $this->decode($this->call($session, 'tools/call', ['name' => '../etc/passwd', 'arguments' => new \stdClass()], 4));

        self::assertSame(-32602, $response['error']['code']);
        self::assertSame('Unknown tool: ../etc/passwd', $response['error']['message']);
    }

    public function testNoTokenIs401AndALaterRevocationIs401OnTheNextRequest(): void
    {
        $created = $this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]);
        $session = $this->session($created);

        $anonymous = $this->post(null, 'tools/list', null, 2, $session['id']);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $anonymous->getStatusCode());
        self::assertSame('Bearer', $anonymous->headers->get('WWW-Authenticate'));

        self::assertSame(Response::HTTP_OK, $this->call($session, 'tools/list', null, 3)->getStatusCode());
        static::getContainer()->get(AiToolTokenService::class)->revoke($created->id, $this->reader->getId());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->call($session, 'tools/list', null, 4)->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->post($created->token, 'initialize', ['protocolVersion' => self::PROTOCOL, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']], 5)->getStatusCode());
    }

    public function testWithTheMcpServerOffEveryRequestIs404(): void
    {
        $created = $this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]);
        $session = $this->session($created);
        ConfigQuery::write('ai_mcp_enabled', '0');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->call($session, 'tools/list', null, 2)->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->post(null, 'tools/list', null, 3)->getStatusCode());
    }

    public function testTheRightsOfTheAdministratorAreReadAtEachCall(): void
    {
        $session = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.product', 'access' => 'UPDATE']]));
        ProfileResourceQuery::create()->filterByProfileId($this->editor->getProfileId())->delete();

        $response = $this->decode($this->call($session, 'tools/call', ['name' => 'thelia_search_products', 'arguments' => ['query' => 'mug']], 3));

        self::assertSame('Unknown tool: thelia_search_products', $response['error']['message']);
    }

    public function testTheEndpointOnlyServesPostAndDeleteWithoutAStream(): void
    {
        $created = $this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]);

        $this->client->request('GET', '/api/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$created->token, 'HTTP_ACCEPT' => 'text/event-stream']);

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $this->client->getResponse()->getStatusCode());
    }

    public function testABatchOfCallsIsRefusedBeforeTheSdkAndProposesNothing(): void
    {
        $session = $this->session($this->token($this->editor, [['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.product', 'access' => 'UPDATE']]));
        $write = fn (int $id): array => ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => 'thelia_update_product_price', 'arguments' => ['pse_id' => $this->pse->getId(), 'price' => '9.00']]];

        $response = $this->postBody($session['token'], json_encode([$write(10), $write(11)], \JSON_THROW_ON_ERROR), $session['id']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(-32600, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['error']['code']);
        self::assertSame(0, AiToolApprovalQuery::create()->count());
    }

    public function testABatchIsRefusedWhateverTheLeadingWhitespace(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));

        $response = $this->postBody($session['token'], " \n\t[]", $session['id']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testABodyOverTheLimitIs413AndOneAtTheLimitIsServed(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));
        $message = static fn (int $size): string => json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping', 'params' => ['padding' => str_repeat('a', $size)]], \JSON_THROW_ON_ERROR);
        $empty = \strlen($message(0));

        $tooLarge = $this->postBody($session['token'], $message(McpRequestGuardListener::MAX_BODY_BYTES - $empty + 1), $session['id']);
        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $tooLarge->getStatusCode());
        self::assertSame(-32600, json_decode((string) $tooLarge->getContent(), true, flags: \JSON_THROW_ON_ERROR)['error']['code']);

        $atTheLimit = $this->postBody($session['token'], $message(McpRequestGuardListener::MAX_BODY_BYTES - $empty), $session['id']);
        self::assertSame(Response::HTTP_OK, $atTheLimit->getStatusCode(), (string) $atTheLimit->getContent());
    }

    public function testAnAnonymousCallerGets401NotAnAnswerAboutItsBody(): void
    {
        $this->client->request('POST', '/api/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat('[', McpRequestGuardListener::MAX_BODY_BYTES + 1));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testTheMethodsTheCoreDoesNotServeAreMethodsThatDoNotExist(): void
    {
        $session = $this->session($this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]));
        $methods = [
            'completion/complete' => ['ref' => ['type' => 'ref/prompt', 'name' => 'any'], 'argument' => ['name' => 'a', 'value' => 'b']],
            'resources/subscribe' => ['uri' => 'file:///any'],
            'resources/unsubscribe' => ['uri' => 'file:///any'],
            'logging/setLevel' => ['level' => 'debug'],
        ];

        foreach (array_keys($methods) as $position => $method) {
            $response = $this->decode($this->call($session, $method, $methods[$method], 20 + $position));

            self::assertSame(-32601, $response['error']['code'], $method);
        }
    }

    public function testTheHostOfTheShopIsServedAndAnUnknownHostIs403(): void
    {
        ConfigQuery::write('url_site', 'https://shop.example.test');
        $created = $this->token($this->reader, [['resource' => 'admin.product', 'access' => 'VIEW']]);

        $shop = $this->postFrom('shop.example.test', $created->token);
        $unknown = $this->postFrom('evil.example.test', $created->token);
        $loopback = $this->postFrom('127.0.0.1:8080', $created->token);

        self::assertSame(Response::HTTP_OK, $shop->getStatusCode(), (string) $shop->getContent());
        self::assertSame(Response::HTTP_FORBIDDEN, $unknown->getStatusCode());
        self::assertSame(Response::HTTP_OK, $loopback->getStatusCode());
    }

    /**
     * @param list<array<string, string>> $scopes
     */
    private function token(Admin $admin, array $scopes): AiCreatedToolToken
    {
        return static::getContainer()->get(AiToolTokenService::class)->create($admin->getId(), 'e2e', $scopes);
    }

    /**
     * Opens a session the way a client does: initialize, then the `initialized` notification.
     *
     * @return array{token: string, id: string}
     */
    private function session(AiCreatedToolToken $created): array
    {
        $response = $this->post($created->token, 'initialize', ['protocolVersion' => self::PROTOCOL, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']], 1);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $id = (string) $response->headers->get('Mcp-Session-Id');
        self::assertNotSame('', $id);

        $this->post($created->token, 'notifications/initialized', null, null, $id);

        return ['token' => $created->token, 'id' => $id];
    }

    /**
     * @param array{token: string, id: string} $session
     * @param array<string, mixed>|null        $params
     */
    private function call(array $session, string $method, ?array $params, int $id): Response
    {
        return $this->post($session['token'], $method, $params, $id, $session['id']);
    }

    private function rpc(AiCreatedToolToken $created, string $method, ?array $params, int $id): Response
    {
        return $this->post($created->token, $method, $params, $id);
    }

    /**
     * @param array<string, mixed>|null $params
     */
    private function post(?string $token, string $method, ?array $params, ?int $id, ?string $sessionId = null): Response
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method];

        if (null !== $id) {
            $message['id'] = $id;
        }

        if (null !== $params) {
            $message['params'] = $params;
        }

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
            $server['HTTP_MCP_PROTOCOL_VERSION'] = self::PROTOCOL;
        }

        $this->client->request('POST', '/api/mcp', server: $server, content: json_encode($message, \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    private function postBody(string $token, string $body, ?string $sessionId = null): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$token];

        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
            $server['HTTP_MCP_PROTOCOL_VERSION'] = self::PROTOCOL;
        }

        $this->client->request('POST', '/api/mcp', server: $server, content: $body);

        return $this->client->getResponse();
    }

    private function postFrom(string $host, string $token): Response
    {
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => self::PROTOCOL, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]];

        $this->client->request('POST', '/api/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_HOST' => $host], content: json_encode($message, \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        self::assertContains($response->getStatusCode(), [Response::HTTP_OK], (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function priceOf(): ?string
    {
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne();

        return ProductPriceQuery::create()->filterByProductSaleElementsId($this->pse->getId())->filterByCurrencyId($currency?->getId())->findOne()?->getPrice();
    }
}

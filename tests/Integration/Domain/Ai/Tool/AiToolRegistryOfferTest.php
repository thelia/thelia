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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Model\AiToolCallLogQuery;
use Thelia\Model\AiToolToken;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * Who is offered which tool: the audience of the usage, the actor, the rights read now, the token of MCP.
 */
final class AiToolRegistryOfferTest extends AiToolTestCase
{
    public function testASuperAdministratorOnAnAdministratorUsageIsOfferedTheAdministratorToolsOnly(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $offer = $this->registry->offer($this->adminContext($admin->getId()));

        self::assertSame(
            ['aiprobemodule_big', 'aiprobemodule_hidden', 'aiprobemodule_modright', 'aiprobemodule_notify', 'aiprobemodule_read', 'aiprobemodule_reviews', 'aiprobemodule_update'],
            $this->names($offer->definitions),
        );
        $offered = $offer->context->offeredTools ?? [];
        sort($offered);
        self::assertSame($this->names($offer->definitions), $offered);
    }

    public function testAnAccessCheckWithoutResourceNorModuleGrantsNothingEvenToASuperAdministrator(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        self::assertFalse($this->access->adminHoldsRight($admin, null, null, 'VIEW'));
        self::assertFalse($this->access->adminHoldsRight($admin, '', '', 'VIEW'));
        self::assertTrue($this->access->adminHoldsRight($admin, 'admin.product', null, 'VIEW'));
    }

    public function testAnAdministratorOnAPublicUsageIsNeverOfferedAnAdministratorTool(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $offer = $this->registry->offer(new AiToolContext(AiActor::admin($admin->getId()), 'aiprobe.tools_public'));

        self::assertSame(['aiprobemodule_catalog'], $this->names($offer->definitions));
    }

    public function testAnAdministratorOnACustomerUsageIsOfferedNothingOfTheCustomerTools(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $offer = $this->registry->offer(new AiToolContext(AiActor::admin($admin->getId()), 'aiprobe.tools_customer'));

        self::assertSame([], $offer->definitions);
    }

    public function testACustomerOnACustomerUsageIsOfferedTheCustomerTools(): void
    {
        $offer = $this->registry->offer(new AiToolContext(AiActor::customer(12), 'aiprobe.tools_customer'));

        self::assertSame(['aiprobemodule_catalog', 'aiprobemodule_orders'], $this->names($offer->definitions));
    }

    public function testAVisitorIsOfferedThePublicToolsOnly(): void
    {
        $offer = $this->registry->offer(new AiToolContext(AiActor::visitor('abc'), 'aiprobe.tools_public'));

        self::assertSame(['aiprobemodule_catalog'], $this->names($offer->definitions));
    }

    public function testTheUsageOfAJobGetsNoTool(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        self::assertSame([], $this->registry->offer(new AiToolContext(AiActor::admin($admin->getId()), 'aiprobe.tools_system'))->definitions);
        self::assertSame([], $this->registry->offer(new AiToolContext(AiActor::system('cli'), 'aiprobe.tools_system'))->definitions);
    }

    public function testACustomerIdentifiedAsAnAdministratorOfTheSameNumberGetsNoAdministratorTool(): void
    {
        $offer = $this->registry->offer(new AiToolContext(AiActor::customer(1), 'aiprobe.tools_admin'));

        self::assertSame([], $offer->definitions);
    }

    public function testAnUnknownUsageIsABugOfTheModule(): void
    {
        $this->expectException(UnknownAiUsageException::class);

        $this->registry->offer(new AiToolContext(AiActor::admin(1), 'aiprobe.nope'));
    }

    public function testTheOnlyListNarrowsTheOffer(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $offer = $this->registry->offer($this->adminContext($admin->getId()), ['aiprobemodule_read', 'aiprobemodule_update', 'unknown_tool']);

        self::assertSame(['aiprobemodule_read', 'aiprobemodule_update'], $this->names($offer->definitions));
    }

    public function testAnAdministratorOnlyGetsTheToolsHisRightsAllow(): void
    {
        $factory = $this->createFixtureFactory();
        $viewer = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $editor = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]]);
        $nobody = $factory->restrictedAdmin([AdminResources::CATEGORY => [AccessManager::VIEW]]);

        $viewerTools = $this->names($this->registry->offer($this->adminContext($viewer->getId()))->definitions);
        $editorTools = $this->names($this->registry->offer($this->adminContext($editor->getId()))->definitions);

        self::assertNotContains('aiprobemodule_update', $viewerTools);
        self::assertContains('aiprobemodule_read', $viewerTools);
        self::assertContains('aiprobemodule_update', $editorTools);
        self::assertSame([], $this->registry->offer($this->adminContext($nobody->getId()))->definitions);
    }

    public function testTheRightOfAModuleToolIsTheOneOfThatModule(): void
    {
        $factory = $this->createFixtureFactory();
        $withoutModuleRight = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);

        self::assertNotContains('aiprobemodule_modright', $this->names($this->registry->offer($this->adminContext($withoutModuleRight->getId()))->definitions));
    }

    public function testTheRightsAreReadAgainAtEachOffer(): void
    {
        $factory = $this->createFixtureFactory();
        $admin = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]]);

        self::assertContains('aiprobemodule_update', $this->names($this->registry->offer($this->adminContext($admin->getId()))->definitions));

        $this->revoke($admin->getProfileId(), AdminResources::PRODUCT);

        self::assertSame([], $this->registry->offer($this->adminContext($admin->getId()))->definitions);
    }

    public function testAToolOutsideTheOfferedListIsUnknownAndTheRefusalIsLogged(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $offer = $this->registry->offer($this->adminContext($admin->getId()), ['aiprobemodule_read']);

        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_update', ['id' => 1, 'value' => 'x']), $offer->context);

        self::assertSame(AiToolCallStatus::UnknownTool, $result->status);
        self::assertSame(['error' => 'unknown_tool', 'message' => 'Unknown tool: aiprobemodule_update'], $result->content);
        self::assertSame(0, \AiProbeModule\ProbeToolState::$proposeCalls);
        self::assertSame('denied', AiToolCallLogQuery::create()->findPk($result->logId)?->getStatus());
    }

    public function testAContextThatWasNeverOfferedAnythingRunsNothing(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_read', ['text' => 'a']), $this->adminContext($admin->getId()));

        self::assertSame(AiToolCallStatus::UnknownTool, $result->status);
        self::assertSame(0, \AiProbeModule\ProbeToolState::$executeCalls);
    }

    public function testANameThatNoToolHasIsUnknownAndLogged(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $offer = $this->registry->offer($this->adminContext($admin->getId()));

        $result = $this->registry->call(new AiToolCall('toolu_01', 'does_not_exist', []), $offer->context);

        self::assertSame(['error' => 'unknown_tool', 'message' => 'Unknown tool: does_not_exist'], $result->content);
        self::assertSame('unknown_tool', AiToolCallLogQuery::create()->findPk($result->logId)?->getStatus());
    }

    public function testARightRevokedAfterTheOfferIsRefusedAtTheCall(): void
    {
        $factory = $this->createFixtureFactory();
        $admin = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $offer = $this->registry->offer($this->adminContext($admin->getId()), ['aiprobemodule_read']);

        $this->revoke($admin->getProfileId(), AdminResources::PRODUCT);
        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_read', ['text' => 'a']), $offer->context);

        self::assertSame(AiToolCallStatus::UnknownTool, $result->status);
        self::assertSame(0, \AiProbeModule\ProbeToolState::$executeCalls);
        self::assertSame('denied', AiToolCallLogQuery::create()->findPk($result->logId)?->getStatus());
    }

    public function testTheTriadeIsReportedOnTheListThatWasOffered(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $context = $this->adminContext($admin->getId());

        self::assertTrue($this->registry->offer($context)->lethalTrifecta);
        self::assertFalse($this->registry->offer($context, ['aiprobemodule_read', 'aiprobemodule_reviews'])->lethalTrifecta);
        self::assertFalse($this->registry->offer($context, ['aiprobemodule_read'])->lethalTrifecta);
        self::assertTrue($this->registry->offer($context, ['aiprobemodule_read', 'aiprobemodule_reviews', 'aiprobemodule_notify'])->lethalTrifecta);
    }

    public function testTheDefinitionsCarryTheSchemaAsAnObject(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        $offer = $this->registry->offer($this->adminContext($admin->getId()), ['aiprobemodule_reviews']);

        self::assertSame('{"type":"object","additionalProperties":false,"properties":{}}', json_encode($offer->definitions[0]->inputSchema));
    }

    public function testDescribeListsEveryDeclaredToolWhoeverMayUseIt(): void
    {
        $descriptors = $this->registry->describe();
        $byName = array_column(array_map(static fn ($descriptor): array => ['name' => $descriptor->name, 'descriptor' => $descriptor], $descriptors), 'descriptor', 'name');

        self::assertCount(\count(self::TOOLS), $descriptors);
        self::assertSame('write', $byName['aiprobemodule_update']->effect->value);
        self::assertSame(AdminResources::PRODUCT, $byName['aiprobemodule_update']->resource);
        self::assertSame('AiProbeModule', $byName['aiprobemodule_update']->module);
        self::assertFalse($byName['aiprobemodule_hidden']->mcp);
    }

    public function testThroughMcpTheScopesOfTheTokenNarrowTheRightsOfTheAdministrator(): void
    {
        ConfigQuery::write('ai_mcp_enabled', '1');
        $factory = $this->createFixtureFactory();
        $admin = $factory->admin();
        $token = $this->token($admin->getId(), scopes: [['resource' => AdminResources::PRODUCT, 'access' => AccessManager::VIEW]]);

        $offer = $this->registry->offer(AiToolContext::forMcp(AiActor::admin($admin->getId()), $token->getId()));

        self::assertSame(['aiprobemodule_big', 'aiprobemodule_notify', 'aiprobemodule_read', 'aiprobemodule_reviews'], $this->names($offer->definitions));
    }

    public function testThroughMcpAToolThatOptedOutIsAbsent(): void
    {
        ConfigQuery::write('ai_mcp_enabled', '1');
        $admin = $this->createFixtureFactory()->admin();
        $token = $this->token($admin->getId(), allRights: true);

        $names = $this->names($this->registry->offer(AiToolContext::forMcp(AiActor::admin($admin->getId()), $token->getId()))->definitions);

        self::assertNotContains('aiprobemodule_hidden', $names);
        self::assertContains('aiprobemodule_update', $names);
    }

    public function testAnMcpCallOfAToolOutsideTheScopesIsAnUnknownTool(): void
    {
        ConfigQuery::write('ai_mcp_enabled', '1');
        $admin = $this->createFixtureFactory()->admin();
        $token = $this->token($admin->getId(), scopes: [['resource' => AdminResources::PRODUCT, 'access' => AccessManager::VIEW]]);
        $context = AiToolContext::forMcp(AiActor::admin($admin->getId()), $token->getId())->withOfferedTools(['aiprobemodule_update']);

        $result = $this->registry->call(new AiToolCall('1', 'aiprobemodule_update', ['id' => 1, 'value' => 'x']), $context);

        self::assertSame(AiToolCallStatus::UnknownTool, $result->status);
        self::assertSame(0, \AiProbeModule\ProbeToolState::$proposeCalls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableTokens(): iterable
    {
        yield 'revoked' => ['revoked'];
        yield 'expired' => ['expired'];
        yield 'of another administrator' => ['foreign'];
        yield 'unknown' => ['unknown'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableTokens')]
    public function testATokenThatIsNotUsableOffersNothing(string $kind): void
    {
        ConfigQuery::write('ai_mcp_enabled', '1');
        $factory = $this->createFixtureFactory();
        $admin = $factory->admin();
        $other = $factory->admin();
        $token = match ($kind) {
            'revoked' => $this->token($admin->getId(), allRights: true, revoked: true),
            'expired' => $this->token($admin->getId(), allRights: true, expiresAt: '-1 day'),
            'foreign' => $this->token($other->getId(), allRights: true),
            default => null,
        };

        $offer = $this->registry->offer(AiToolContext::forMcp(AiActor::admin($admin->getId()), $token?->getId() ?? 999999999));

        self::assertSame([], $offer->definitions);
    }

    public function testMcpIsClosedUntilTheMerchantOpensIt(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $token = $this->token($admin->getId(), allRights: true);
        $context = AiToolContext::forMcp(AiActor::admin($admin->getId()), $token->getId());

        self::assertSame([], $this->registry->offer($context)->definitions);

        ConfigQuery::write('ai_mcp_enabled', '1');
        self::assertNotSame([], $this->registry->offer($context)->definitions);

        ConfigQuery::write('ai_enabled', '0');
        self::assertSame([], $this->registry->offer($context)->definitions);
    }

    private function adminContext(int $adminId): AiToolContext
    {
        return new AiToolContext(AiActor::admin($adminId), 'aiprobe.tools_admin', 'aiprobe.conversation.'.bin2hex(random_bytes(4)), 'fr_FR');
    }

    /**
     * @param list<\Thelia\Domain\Ai\AiToolDefinition> $definitions
     *
     * @return list<string>
     */
    private function names(array $definitions): array
    {
        $names = array_map(static fn ($definition): string => $definition->name, $definitions);
        sort($names);

        return $names;
    }

    private function revoke(int $profileId, string $resourceCode): void
    {
        $resource = \Thelia\Model\ResourceQuery::create()->findOneByCode($resourceCode);

        \Thelia\Model\ProfileResourceQuery::create()
            ->filterByProfileId($profileId)
            ->filterByResourceId($resource?->getId())
            ->delete();
    }

    /**
     * @param list<array<string, string>>|null $scopes
     */
    private function token(int $adminId, bool $allRights = false, ?array $scopes = null, bool $revoked = false, string $expiresAt = '+30 days'): AiToolToken
    {
        $token = (new AiToolToken())
            ->setAdminId($adminId)
            ->setLabel('test')
            ->setTokenHash(hash('sha256', random_bytes(16)))
            ->setTokenPrefix('thelia_mcp_abcd')
            ->setAllRights($allRights)
            ->setScopes(null === $scopes ? null : json_encode($scopes, \JSON_THROW_ON_ERROR))
            ->setExpiresAt(new \DateTimeImmutable($expiresAt));

        if ($revoked) {
            $token->setRevokedAt(new \DateTimeImmutable());
        }

        $token->save();

        return $token;
    }
}

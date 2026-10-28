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
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\Exception\AiToolTokenException;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenHasher;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenService;
use Thelia\Model\Admin;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\AiToolToken;
use Thelia\Model\AiToolTokenQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * The life of an MCP token: creation, what is stored, expiry, scopes, revocation.
 */
final class AiToolTokenServiceTest extends AiToolTestCase
{
    private AiToolTokenService $tokens;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = new AiToolTokenService(new AiToolTokenHasher(), $this->access, new AiSettings(), $this->approvalRows);
        $this->owner = $this->createFixtureFactory()->restrictedAdmin([AdminResources::AI_MCP => [AccessManager::CREATE]], ['login' => 'marie']);
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('ai_tool_token_max_days', '365');

        parent::tearDown();
    }

    public function testTheClassesTheBackOfficeInjectsAreNotMarkedInternal(): void
    {
        foreach ([AiToolTokenService::class, \Thelia\Domain\Ai\Tool\Mcp\McpAllowedHosts::class] as $class) {
            self::assertStringNotContainsString('@internal', (string) (new \ReflectionClass($class))->getDocComment(), $class);
        }
    }

    public function testOnlyTheFingerprintAndThePrefixAreStored(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'Claude Code', [['resource' => 'admin.product', 'access' => 'VIEW']]);

        $row = AiToolTokenQuery::create()->findPk($created->id);

        self::assertInstanceOf(AiToolToken::class, $row);
        self::assertSame(hash('sha256', $created->token), $row->getTokenHash());
        self::assertSame(substr($created->token, 0, 15), $row->getTokenPrefix());
        self::assertSame($created->prefix, $row->getTokenPrefix());
        self::assertSame(1, preg_match('/^thelia_mcp_[0-9A-Za-z]{43}$/D', $created->token));

        foreach ($row->toArray() as $column => $value) {
            self::assertStringNotContainsString($created->token, (string) json_encode($value), 'The clear token is stored in '.$column.'.');
        }

        self::assertSame($this->owner->getId(), $row->getAdminId());
        self::assertSame('Claude Code', $row->getLabel());
        self::assertFalse((bool) $row->getAllRights());
        self::assertSame('[{"resource":"admin.product","access":"VIEW"}]', $row->getScopes());
    }

    public function testCreatingATokenTakesTheRightOfTheMcpTokens(): void
    {
        $viewerOnly = $this->createFixtureFactory()->restrictedAdmin([AdminResources::AI_MCP => [AccessManager::VIEW]]);
        $nothing = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $before = AiToolTokenQuery::create()->count();

        foreach ([$viewerOnly, $nothing] as $admin) {
            try {
                $this->tokens->create($admin->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);
                self::fail('The token was created without the right.');
            } catch (AiToolTokenException $exception) {
                self::assertStringContainsString('admin.configuration.ai-mcp', $exception->getMessage());
            }
        }

        self::assertSame($before, AiToolTokenQuery::create()->count());
        self::assertGreaterThan(0, $this->tokens->create($this->owner->getId(), 'ok', [['module' => 'CommerceAgents', 'access' => 'VIEW']])->id);
    }

    public function testAnUnknownAdministratorCreatesNothing(): void
    {
        $this->expectException(AiToolTokenException::class);

        $this->tokens->create(999999999, 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);
    }

    public function testScopesAreRequiredUnlessAllRightsIsChosen(): void
    {
        foreach ([[], [['access' => 'VIEW']], [['resource' => 'admin.nothing', 'access' => 'VIEW']], [['resource' => 'admin.product', 'access' => 'ROOT']],
            [['resource' => 'admin.product', 'module' => 'Cart', 'access' => 'VIEW']], [['module' => 'bad module', 'access' => 'VIEW']], ['text']] as $scopes) {
            try {
                $this->tokens->create($this->owner->getId(), 'x', $scopes);
                self::fail('The scopes were accepted: '.json_encode($scopes));
            } catch (AiToolTokenException) {
                self::assertTrue(true);
            }
        }

        $all = $this->tokens->create($this->owner->getId(), 'all', [], true);
        $row = AiToolTokenQuery::create()->findPk($all->id);

        self::assertTrue((bool) $row?->getAllRights());
        self::assertNull($row?->getScopes());
    }

    public function testAllRightsAndScopesAreNotMixed(): void
    {
        $this->expectException(AiToolTokenException::class);
        $this->expectExceptionCode(AiToolTokenException::ALL_RIGHTS_WITH_SCOPES);

        $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']], true);
    }

    public function testDuplicateScopesAreStoredOnce(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW'], ['resource' => 'admin.product', 'access' => 'VIEW'], ['module' => 'Cart', 'access' => 'UPDATE']]);

        self::assertSame('[{"resource":"admin.product","access":"VIEW"},{"module":"Cart","access":"UPDATE"}]', AiToolTokenQuery::create()->findPk($created->id)?->getScopes());
    }

    public function testTheLabelHoldsOneToSixtyFourCharacters(): void
    {
        foreach (['', '   ', str_repeat('a', 65)] as $label) {
            try {
                $this->tokens->create($this->owner->getId(), $label, [['resource' => 'admin.product', 'access' => 'VIEW']]);
                self::fail('The label was accepted.');
            } catch (AiToolTokenException) {
                self::assertTrue(true);
            }
        }

        self::assertGreaterThan(0, $this->tokens->create($this->owner->getId(), str_repeat('a', 64), [['resource' => 'admin.product', 'access' => 'VIEW']])->id);
    }

    public function testATokenExpiresIn90DaysByDefault(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);

        self::assertEqualsWithDelta((new \DateTimeImmutable('+90 days'))->getTimestamp(), $created->expiresAt->getTimestamp(), 5);
        self::assertEqualsWithDelta($created->expiresAt->getTimestamp(), AiToolTokenQuery::create()->findPk($created->id)?->getExpiresAt()?->getTimestamp(), 1);
    }

    public function testTheExpiryNeverPassesTheCeilingOfTheSettings(): void
    {
        ConfigQuery::write('ai_tool_token_max_days', '30');

        $asked = $this->tokens->create($this->owner->getId(), 'long', [['resource' => 'admin.product', 'access' => 'VIEW']], false, new \DateTimeImmutable('+300 days'));
        $default = $this->tokens->create($this->owner->getId(), 'default', [['resource' => 'admin.product', 'access' => 'VIEW']]);
        $short = $this->tokens->create($this->owner->getId(), 'short', [['resource' => 'admin.product', 'access' => 'VIEW']], false, new \DateTimeImmutable('+7 days'));

        self::assertEqualsWithDelta((new \DateTimeImmutable('+30 days'))->getTimestamp(), $asked->expiresAt->getTimestamp(), 5);
        self::assertEqualsWithDelta((new \DateTimeImmutable('+30 days'))->getTimestamp(), $default->expiresAt->getTimestamp(), 5);
        self::assertEqualsWithDelta((new \DateTimeImmutable('+7 days'))->getTimestamp(), $short->expiresAt->getTimestamp(), 5);
    }

    public function testAnExpiryInThePastIsRefused(): void
    {
        $this->expectException(AiToolTokenException::class);

        $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']], false, new \DateTimeImmutable('-1 minute'));
    }

    public function testAPresentedTokenFindsItsRowUntilItIsRevokedOrExpired(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);

        self::assertSame($created->id, $this->tokens->authenticate($created->token)?->getId());
        self::assertNull($this->tokens->authenticate('thelia_mcp_'.str_repeat('0', 43)), 'unknown');
        self::assertNull($this->tokens->authenticate('garbage'), 'malformed');
        self::assertNull($this->tokens->authenticate(''), 'empty');

        AiToolTokenQuery::create()->filterById($created->id)->update(['ExpiresAt' => new \DateTimeImmutable('-1 second')]);
        self::assertNull($this->tokens->authenticate($created->token), 'expired');

        AiToolTokenQuery::create()->filterById($created->id)->update(['ExpiresAt' => new \DateTimeImmutable('+1 day')]);
        self::assertNotNull($this->tokens->authenticate($created->token));

        $this->tokens->revoke($created->id, $this->owner->getId());
        self::assertNull($this->tokens->authenticate($created->token), 'revoked');
    }

    public function testATokenOfADeletedAdministratorIsNotFound(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);

        $this->owner->delete();

        self::assertNull($this->tokens->authenticate($created->token));
    }

    public function testRevokingRejectsWhatWasProposedThroughTheToken(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'UPDATE']]);
        $through = $this->proposeThrough($created->id);
        $direct = $this->proposeThrough(null);
        $otherToken = $this->tokens->create($this->owner->getId(), 'other', [['resource' => 'admin.product', 'access' => 'UPDATE']]);
        $other = $this->proposeThrough($otherToken->id);

        $this->tokens->revoke($created->id, $this->owner->getId());

        $row = AiToolTokenQuery::create()->findPk($created->id);
        self::assertNotNull($row?->getRevokedAt());
        $rejected = AiToolApprovalQuery::create()->findPk($through);
        self::assertSame('rejected', $rejected?->getStatus());
        self::assertSame('token_revoked', $rejected?->getDecisionNote());
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($direct)?->getStatus());
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($other)?->getStatus());
    }

    public function testRevokingTwiceKeepsTheFirstMoment(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);
        AiToolTokenQuery::create()->filterById($created->id)->update(['RevokedAt' => new \DateTimeImmutable('2026-01-02 03:04:05')]);

        $this->tokens->revoke($created->id, $this->owner->getId());

        self::assertSame('2026-01-02 03:04:05', AiToolTokenQuery::create()->findPk($created->id)?->getRevokedAt('Y-m-d H:i:s'));
    }

    public function testAnAdministratorRevokesHisOwnTokensAndOthersNeedTheAiRight(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);
        $stranger = $this->createFixtureFactory()->restrictedAdmin([AdminResources::AI => [AccessManager::VIEW]]);
        $manager = $this->createFixtureFactory()->restrictedAdmin([AdminResources::AI => [AccessManager::UPDATE]]);

        try {
            $this->tokens->revoke($created->id, $stranger->getId());
            self::fail('The token of another was revoked without the right.');
        } catch (AiToolTokenException) {
            self::assertNull(AiToolTokenQuery::create()->findPk($created->id)?->getRevokedAt());
        }

        $this->tokens->revoke($created->id, $manager->getId());

        self::assertNotNull(AiToolTokenQuery::create()->findPk($created->id)?->getRevokedAt());
    }

    public function testRevokingAnUnknownTokenIsAnError(): void
    {
        $this->expectException(AiToolTokenException::class);

        $this->tokens->revoke(999999999, $this->owner->getId());
    }

    public function testTheLastUseIsWrittenAtMostOnceAMinute(): void
    {
        $created = $this->tokens->create($this->owner->getId(), 'x', [['resource' => 'admin.product', 'access' => 'VIEW']]);
        $row = $this->tokens->authenticate($created->token);
        self::assertNull($row?->getLastUsedAt());

        $this->tokens->markUsed($row);
        $first = AiToolTokenQuery::create()->findPk($created->id)?->getLastUsedAt('Y-m-d H:i:s');
        self::assertNotNull($first);

        AiToolTokenQuery::create()->filterById($created->id)->update(['LastUsedAt' => new \DateTimeImmutable('-1 hour')]);
        $again = $this->tokens->authenticate($created->token);
        $this->tokens->markUsed($again);
        self::assertEqualsWithDelta(time(), AiToolTokenQuery::create()->findPk($created->id)?->getLastUsedAt()?->getTimestamp(), 5);

        $before = AiToolTokenQuery::create()->findPk($created->id)?->getLastUsedAt('Y-m-d H:i:s');
        $this->tokens->markUsed($this->tokens->authenticate($created->token));
        self::assertSame($before, AiToolTokenQuery::create()->findPk($created->id)?->getLastUsedAt('Y-m-d H:i:s'));
    }

    private function proposeThrough(?int $tokenId): int
    {
        $proposer = $this->createFixtureFactory()->admin();
        $context = new AiToolContext(AiActor::admin($proposer->getId()), 'aiprobe.tools_admin', 'aiprobe.conversation.88', 'fr_FR');
        $offer = $this->registry->offer($context, ['aiprobemodule_update']);
        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_update', ['id' => 7, 'value' => 'new']), $offer->context);

        self::assertNotNull($result->approvalId);

        if (null !== $tokenId) {
            AiToolApprovalQuery::create()->filterById($result->approvalId)->update(['TokenId' => $tokenId]);
        }

        return $result->approvalId;
    }
}

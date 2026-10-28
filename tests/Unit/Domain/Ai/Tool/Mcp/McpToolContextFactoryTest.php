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

namespace Thelia\Tests\Unit\Domain\Ai\Tool\Mcp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Tool\AiToolChannel;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\Mcp\McpToolContextFactory;
use Thelia\Domain\Ai\Tool\Security\AiToolTokenAuthenticator;
use Thelia\Model\Admin;

/**
 * The context of an MCP call is the last barrier of a handler reached outside the token firewall: it answers
 * nothing unless the firewall authenticated an administrator with an MCP token.
 */
final class McpToolContextFactoryTest extends TestCase
{
    public function testAnEmptyTokenStorageGivesNoContext(): void
    {
        self::assertNull((new McpToolContextFactory(new TokenStorage()))->create());
    }

    public function testATokenWithoutTheTokenIdAttributeGivesNoContext(): void
    {
        self::assertNull($this->create($this->administrator(), []));
    }

    public function testAnAttributeThatIsNoStoredTokenIdGivesNoContext(): void
    {
        self::assertNull($this->create($this->administrator(), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => '7']));
        self::assertNull($this->create($this->administrator(), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => 0]));
    }

    public function testAUserWhoIsNoAdministratorGivesNoContext(): void
    {
        self::assertNull($this->create(new InMemoryUser('customer', null, ['ROLE_ADMIN']), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => 7]));
    }

    public function testAnAdministratorWithAnMcpTokenGivesTheMcpContext(): void
    {
        $context = $this->create($this->administrator(), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => 7]);

        self::assertNotNull($context);
        self::assertSame(AiToolChannel::Mcp, $context->channel);
        self::assertSame(7, $context->tokenId);
        self::assertSame(AiActorType::Admin, $context->actor->type);
        self::assertSame('42', $context->actor->identifier);
    }

    public function testTheContextSpeaksTheLanguageOfTheAdministratorOfTheToken(): void
    {
        $context = $this->create($this->administrator()->setLocale('fr_FR'), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => 7]);

        self::assertSame('fr_FR', $context?->locale);
    }

    public function testAnAdministratorWithoutALocaleGetsEnglish(): void
    {
        $context = $this->create($this->administrator()->setLocale(''), [AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE => 7]);

        self::assertSame('en_US', $context?->locale);
    }

    private function administrator(): Admin
    {
        return (new Admin())->setId(42)->setLogin('marie')->setLocale('en_US');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function create(UserInterface $user, array $attributes): ?AiToolContext
    {
        $storage = new TokenStorage();
        $token = new PostAuthenticationToken($user, 'mcp', ['ROLE_ADMIN']);
        $token->setAttributes($attributes);
        $storage->setToken($token);

        return (new McpToolContextFactory($storage))->create();
    }
}

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

namespace Thelia\Domain\Ai\Tool\Mcp;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\Security\AiToolTokenAuthenticator;
use Thelia\Model\Admin;

/**
 * The context of a call that came in through the MCP endpoint, built from what the token firewall authenticated:
 * the administrator the MCP token belongs to and the id of that token. Null when the request carries neither,
 * so that a handler reached outside the firewall answers nothing.
 *
 * @internal
 */
final readonly class McpToolContextFactory
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function create(): ?AiToolContext
    {
        $token = $this->tokenStorage->getToken();
        $admin = $token?->getUser();

        if (null === $token || !$admin instanceof Admin || !$token->hasAttribute(AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE)) {
            return null;
        }

        $tokenId = $token->getAttribute(AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE);

        if (!\is_int($tokenId) || $tokenId < 1 || null === $admin->getId()) {
            return null;
        }

        return AiToolContext::forMcp(AiActor::admin($admin->getId()), $tokenId);
    }
}

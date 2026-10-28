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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Domain\Ai\Tool\Security\AiToolTokenAuthenticator;
use Thelia\Domain\Ai\Tool\Service\AiToolAccessChecker;
use Thelia\Model\Admin;

/**
 * The stand-in of the MCP endpoint: answers who the firewall authenticated, which token it carries, and whether
 * the right named in the query is held by the administrator, covered by the token, and both (what a tool needs).
 */
final readonly class McpProbeController
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private AiToolAccessChecker $access,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokens->getToken();
        $admin = $token?->getUser();
        $tokenId = $token?->getAttribute(AiToolTokenAuthenticator::TOKEN_ID_ATTRIBUTE);

        if (!$admin instanceof Admin || !\is_int($tokenId)) {
            return new JsonResponse(['authenticated' => false], 500);
        }

        $resource = $request->query->getString('resource');
        $access = $request->query->getString('access', 'VIEW');

        return new JsonResponse([
            'login' => $admin->getLogin(),
            'tokenId' => $tokenId,
            'adminHolds' => $this->access->adminHoldsRight($admin->getId(), $resource, null, $access),
            'tokenCovers' => $this->access->tokenCovers($tokenId, $admin->getId(), $resource, null, $access),
        ]);
    }
}

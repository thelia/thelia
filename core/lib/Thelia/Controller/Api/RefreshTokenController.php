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

namespace Thelia\Controller\Api;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Thelia\Core\Security\RefreshToken\RefreshTokenService;
use Thelia\Core\Security\UserProvider\AdminUserProvider;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Model\Admin;

/**
 * Token refresh endpoints.
 *
 * Each scope (admin / customer) has its own URL so the firewall config
 * stays explicit. The handler validates the refresh token, loads the
 * user via the matching provider, issues a fresh access + refresh pair
 * and returns it. The previous refresh token is single-use and already
 * removed by the time we respond.
 */
#[AsController]
final readonly class RefreshTokenController
{
    public function __construct(
        private RefreshTokenService $refreshTokens,
        private JWTTokenManagerInterface $jwtManager,
        private AdminUserProvider $adminProvider,
        private CustomerUserProvider $customerProvider,
        private AdminTwoFactorManager $twoFactorManager,
    ) {
    }

    #[Route(
        path: '/api/admin/token/refresh',
        name: 'api_admin_token_refresh',
        methods: ['POST'],
    )]
    public function refreshAdmin(Request $request): JsonResponse
    {
        return $this->refresh($request, RefreshTokenService::SCOPE_ADMIN);
    }

    #[Route(
        path: '/api/front/token/refresh',
        name: 'api_front_token_refresh',
        methods: ['POST'],
    )]
    public function refreshFront(Request $request): JsonResponse
    {
        return $this->refresh($request, RefreshTokenService::SCOPE_CUSTOMER);
    }

    private function refresh(Request $request, string $expectedScope): JsonResponse
    {
        $token = $this->extractRefreshToken($request);
        if ($token === null) {
            return new JsonResponse(['message' => 'Missing refresh_token.'], 400);
        }

        $payload = $this->refreshTokens->consume($token);
        if ($payload === null) {
            return new JsonResponse(['message' => 'Invalid or expired refresh token.'], 401);
        }

        if ($payload['scope'] !== $expectedScope) {
            return new JsonResponse(['message' => 'Refresh token does not match this endpoint.'], 401);
        }

        try {
            $user = $this->loadUser($payload['username'], $expectedScope);
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Invalid or expired refresh token.'], 401);
        }

        $secondFactorMark = $user instanceof Admin ? $this->twoFactorManager->enrolmentMarkOf($user) : null;

        if ($user instanceof Admin && ($this->twoFactorManager->mustEnrol($user) || $secondFactorMark !== ($payload['second_factor'] ?? null))) {
            return new JsonResponse(['message' => 'Invalid or expired refresh token.'], 401);
        }

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $this->refreshTokens->issue($payload['username'], $expectedScope, $secondFactorMark),
            'refresh_token_ttl' => $this->refreshTokens->ttl(),
        ]);
    }

    private function extractRefreshToken(Request $request): ?string
    {
        $contentType = (string) $request->headers->get('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            try {
                $payload = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            $value = $payload['refresh_token'] ?? null;
        } else {
            $value = $request->request->get('refresh_token');
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function loadUser(string $username, string $scope): UserInterface
    {
        return $scope === RefreshTokenService::SCOPE_ADMIN
            ? $this->adminProvider->loadUserByIdentifier($username)
            : $this->customerProvider->loadUserByIdentifier($username);
    }
}

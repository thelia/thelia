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

namespace Thelia\Domain\Ai\Tool\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenRateLimiter;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenService;
use Thelia\Model\Admin;

/**
 * Authenticates a request to the MCP endpoint (`^/api/mcp`) with `Authorization: Bearer <token>` (the scheme is read whatever its case, RFC 7235).
 *
 * The token is a dedicated one (never the JWT of an administrator): it is looked up by its fingerprint, and the
 * request is turned away with a 401 when it is unknown, revoked or expired, or when its administrator is gone.
 * With the MCP server off, every request gets a 404 before anything else is read. The administrator is loaded
 * from the database on each request, and the id of the token rides on the authenticated token of the firewall
 * (attribute {@see self::TOKEN_ID_ATTRIBUTE}), for the MCP handler that builds `AiToolContext::forMcp()`.
 *
 * @internal
 */
final class AiToolTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string TOKEN_ID_ATTRIBUTE = 'thelia_ai_tool_token_id';

    private const string PASSPORT_ATTRIBUTE = 'thelia_ai_tool_token_id';

    public function __construct(
        private readonly AiSettings $settings,
        private readonly AiToolTokenService $tokens,
        private readonly AiToolTokenRateLimiter $limiter,
    ) {
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        if (!$this->settings->isEnabled() || !$this->settings->mcpEnabled()) {
            throw AiToolTokenAuthenticationException::disabled();
        }

        $header = (string) $request->headers->get('Authorization', '');

        if (1 !== preg_match('/^Bearer (\S+)$/Di', $header, $matches)) {
            throw AiToolTokenAuthenticationException::rejected();
        }

        $token = $this->tokens->authenticate($matches[1]);
        $admin = null === $token ? null : $this->tokens->administratorOf($token);

        if (null === $token || !$admin instanceof Admin) {
            throw AiToolTokenAuthenticationException::rejected();
        }

        $retryAfter = $this->limiter->consume($token->getId());

        if (null !== $retryAfter) {
            throw AiToolTokenAuthenticationException::rateLimited($retryAfter);
        }

        $this->tokens->markUsed($token);

        $passport = new SelfValidatingPassport(new UserBadge($admin->getUserIdentifier(), static fn (): Admin => $admin));
        $passport->setAttribute(self::PASSPORT_ATTRIBUTE, $token->getId());

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();
        $token = new PostAuthenticationToken($user, $firewallName, $user->getRoles());
        $token->setAttribute(self::TOKEN_ID_ATTRIBUTE, $passport->getAttribute(self::PASSPORT_ATTRIBUTE));

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof AiToolTokenAuthenticationException && $exception->isDisabled()) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        if ($exception instanceof AiToolTokenAuthenticationException && null !== $exception->retryAfter()) {
            $seconds = max(1, $exception->retryAfter()->getTimestamp() - time());

            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $seconds]);
        }

        return $this->unauthorized();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized();
    }

    private function unauthorized(): Response
    {
        return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }
}

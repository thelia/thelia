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

namespace Thelia\Tools;

use Random\RandomException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\Exception\TokenAuthenticationException;

/**
 * Class TokenProvider.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TokenProvider
{
    /** Name of the request body field (and, deprecated, of the query string parameter) carrying the token. */
    public const TOKEN_FIELD = '_token';

    /** Name of the HTTP header a script sends the token in. */
    public const TOKEN_HEADER = 'X-CSRF-Token';

    protected ?string $token = null;
    protected ?SessionInterface $session = null;

    /**
     * @param bool $acceptTokenInQueryString a token read from the URL leaks through access logs, browser
     *                                       history and Referer headers; it is still accepted, with a
     *                                       deprecation, until the back offices and modules send it in
     *                                       the body or the header. Set the `thelia.token.accept_query_string`
     *                                       parameter to false to refuse it.
     */
    public function __construct(
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
        #[Autowire(param: 'thelia.token_id')]
        protected string $tokenName,
        #[Autowire(param: 'thelia.token.accept_query_string')]
        protected bool $acceptTokenInQueryString = true,
    ) {
        $this->assignTokenFromSession();
    }

    private function assignTokenFromSession(): void
    {
        if (null !== $this->token) {
            return;
        }

        $session = $this->requestStack->getMainRequest()?->getSession();

        if ($session instanceof SessionInterface) {
            $this->token = $session->get($this->tokenName);
        }
    }

    /**
     * @throws RandomException
     */
    public function assignToken(): ?string
    {
        if (null === $this->token) {
            $this->token = $this->getToken();
            $session = $this->requestStack->getMainRequest()?->getSession();

            if ($session instanceof SessionInterface) {
                $session->set($this->tokenName, $this->token);
            }
        }

        return $this->token;
    }

    /**
     * Check the token a caller already read from the request.
     *
     * When that value only travels in the URL of the current request, the check emits a
     * deprecation (or fails, once the query string is refused): read the token with
     * checkRequestToken() instead, which looks in the body and the header first.
     *
     * @throws TokenAuthenticationException
     */
    public function checkToken(string $entryValue): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request instanceof Request && $this->isOnlyInQueryString($request, $entryValue)) {
            $this->guardQueryStringToken();
        }

        return $this->assertTokenMatches($entryValue);
    }

    /**
     * Read the token from the request body (`_token`), then from the `X-CSRF-Token` header,
     * then, deprecated, from the query string, and check it.
     *
     * @throws TokenAuthenticationException
     */
    public function checkRequestToken(Request $request): bool
    {
        return $this->assertTokenMatches($this->getTokenFromRequest($request));
    }

    /**
     * @throws TokenAuthenticationException
     */
    public function getTokenFromRequest(Request $request): string
    {
        $bodyToken = $request->request->get(self::TOKEN_FIELD);

        if (\is_string($bodyToken) && '' !== $bodyToken) {
            return $bodyToken;
        }

        $headerToken = $request->headers->get(self::TOKEN_HEADER);

        if (\is_string($headerToken) && '' !== $headerToken) {
            return $headerToken;
        }

        $queryToken = $request->query->get(self::TOKEN_FIELD);

        if (\is_string($queryToken) && '' !== $queryToken) {
            $this->guardQueryStringToken();

            return $queryToken;
        }

        throw new TokenAuthenticationException('Tried to validate a request without token');
    }

    /**
     * @throws TokenAuthenticationException
     */
    private function assertTokenMatches(string $entryValue): bool
    {
        $this->assignTokenFromSession();

        if (null === $this->token) {
            throw new TokenAuthenticationException('Tried to check a token without assigning it before');
        }

        if (!hash_equals($this->token, $entryValue)) {
            throw new TokenAuthenticationException('Tried to validate an invalid token');
        }

        return true;
    }

    private function isOnlyInQueryString(Request $request, string $entryValue): bool
    {
        return ($request->query->all()[self::TOKEN_FIELD] ?? null) === $entryValue
            && ($request->request->all()[self::TOKEN_FIELD] ?? null) !== $entryValue
            && $request->headers->get(self::TOKEN_HEADER) !== $entryValue;
    }

    /**
     * @throws TokenAuthenticationException
     */
    private function guardQueryStringToken(): void
    {
        if (!$this->acceptTokenInQueryString) {
            throw new TokenAuthenticationException('Tried to validate a token sent in the URL');
        }

        trigger_deprecation(
            'thelia/core',
            '3.2',
            'Sending the CSRF token in the "%s" query string parameter is deprecated, send it in the request body or in the "%s" header instead.',
            self::TOKEN_FIELD,
            self::TOKEN_HEADER,
        );
    }

    /**
     * @throws RandomException
     */
    protected function refreshToken(): void
    {
        $this->token = null;
        $this->assignToken();
    }

    /**
     * @throws RandomException
     */
    public function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * @alias getToken
     *
     * @throws RandomException
     */
    public static function generateToken(): string
    {
        return md5(random_bytes(32));
    }
}

<?php

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
    protected ?string $token = null;
    protected ?SessionInterface $session = null;
    protected ?string $tokenName;

    public function __construct(
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
        string $tokenName = null
    ) {
        $this->tokenName = $tokenName;
        $this->token = $this->getStoredToken();
    }

    /**
     * The session is not necessarily available when this service is instantiated, so it has
     * to be looked up again on each access.
     */
    private function setSessionFromRequest(): void
    {
        $currentRequest = $this->requestStack?->getCurrentRequest();
        if ($currentRequest && $currentRequest->hasSession()) {
            $session = $this->requestStack->getSession();
            $this->session = $session->isStarted() ? $session : null;
        } else {
            $this->session = null;
        }
    }

    private function getStoredToken(): ?string
    {
        $this->setSessionFromRequest();

        if (null === $this->session || null === $this->tokenName) {
            return null;
        }

        return $this->session->get($this->tokenName);
    }

    private function storeToken(string $token): void
    {
        $this->setSessionFromRequest();

        $this->token = $token;

        if (null !== $this->tokenName) {
            $this->session?->set($this->tokenName, $token);
        }
    }

    /**
     * @throws RandomException
     */
    public function assignToken(): ?string
    {
        if (null !== $storedToken = $this->getStoredToken()) {
            return $this->token = $storedToken;
        }

        $this->storeToken($this->token ?? $this->getToken());

        return $this->token;
    }

    /**
     * @throws TokenAuthenticationException
     */
    public function checkToken(?string $entryValue): bool
    {
        $this->token = $this->getStoredToken() ?? $this->token;

        if (null === $this->token) {
            throw new TokenAuthenticationException(
                'Tried to check a token without assigning it before'
            );
        }
        if ($this->token !== $entryValue) {
            throw new TokenAuthenticationException(
                'Tried to validate an invalid token'
            );
        }

        return true;
    }

    /**
     * @throws RandomException
     */
    protected function refreshToken(): void
    {
        $this->storeToken($this->getToken());
    }

    /**
     * @throws RandomException
     */
    public function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * Same method as getToken but can be called statically.
     *
     * @alias getToken
     *
     * @throws RandomException
     */
    public static function generateToken(): string
    {
        return md5(random_bytes(32));
    }
}

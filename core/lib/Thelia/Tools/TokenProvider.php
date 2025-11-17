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
        $this->setSessionFromRequest();
        $this->tokenName = $tokenName;

        if (null !== $this->session && null !== $this->tokenName) {
            $this->token = $this->session->get($this->tokenName);
        }
    }

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

    /**
     * @throws RandomException
     */
    public function assignToken(): ?string
    {
        if (null !== $this->token) {
            return $this->token;
        }

        $this->token = $this->getToken();
        if (null !== $this->tokenName) {
            $this->session?->set($this->tokenName, $this->token);
        }

        return $this->token;
    }

    /**
     * @throws TokenAuthenticationException
     */
    public function checkToken(?string $entryValue): bool
    {
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

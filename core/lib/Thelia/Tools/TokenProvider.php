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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Exception\TokenAuthenticationException;

/**
 * Class TokenProvider.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TokenProvider
{
    /**
     * @var string The stored token for this page
     */
    protected $token;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var TranslatorInterface The translator
     */
    protected $translator;

    /**
     * @var string the current name of the token
     */
    protected $tokenName;

    public function __construct(RequestStack $requestStack, TranslatorInterface $translator, $tokenName)
    {
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->tokenName = $tokenName;
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (null !== $session) {
            $this->token = $session->get($this->tokenName);
        }
    }

    private function setSessionFromRequest(): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest && $currentRequest->hasSession()) {
            $session = $this->requestStack->getSession();
            $this->session = $session->isStarted() ? $session : null;
        } else {
            $this->session = null;
        }
    }

    /**
     * @return string
     */
    public function assignToken()
    {
        if (null === $this->token) {
            $this->token = $this->getToken();
            $session = $this->requestStack->getCurrentRequest()?->getSession();
            if (null !== $session) {
                $session->set($this->tokenName, $this->token);
            }
        }

        return $this->token;
    }

    /**
     * @param string $entryValue
     *
     * @throws \Thelia\Core\Security\Exception\TokenAuthenticationException
     *
     * @return bool
     */
    public function checkToken($entryValue)
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
     * @return string
     */
    public function getToken()
    {
        return self::generateToken();
    }

    /**
     * Same method as getToken but can be called statically.
     *
     * @alias getToken
     *
     * @return string
     */
    public static function generateToken()
    {
        return md5(random_bytes(32));
    }
}

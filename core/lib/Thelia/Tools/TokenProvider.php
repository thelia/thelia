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

    public function __construct(
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
        #[Autowire(param: 'thelia.token_id')]
        protected string $tokenName,
    ) {
        $this->assignTokenFromSession();
    }

    private function assignTokenFromSession(): void
    {
        if (null !== $this->token) {
            return;
        }

        $session = $this->requestStack->getCurrentRequest()?->getSession();

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
            $session = $this->requestStack->getCurrentRequest()?->getSession();

            if ($session instanceof SessionInterface) {
                $session->set($this->tokenName, $this->token);
            }
        }

        return $this->token;
    }

    /**
     * @throws TokenAuthenticationException
     */
    public function checkToken(string $entryValue): bool
    {
        $this->assignTokenFromSession();

        if (null === $this->token) {
            throw new TokenAuthenticationException('Tried to check a token without assigning it before');
        }

        if ($this->token !== $entryValue) {
            throw new TokenAuthenticationException('Tried to validate an invalid token');
        }

        return true;
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

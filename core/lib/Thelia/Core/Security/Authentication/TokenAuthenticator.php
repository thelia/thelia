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

namespace Thelia\Core\Security\Authentication;

use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\UserProvider\TokenUserProvider;

class TokenAuthenticator implements AuthenticatorInterface
{
    protected $key;

    protected $userProvider;

    public function __construct($key, TokenUserProvider $userProvider)
    {
        $this->key = $key;

        $this->userProvider = $userProvider;
    }

    /**
     * @see \Thelia\Core\Security\Authentication\AuthenticatorInterface::getAuthentifiedUser()
     */
    public function getAuthentifiedUser()
    {
        $keyData = $this->userProvider->decodeKey($this->key);

        $user = $this->userProvider->getUser($keyData);

        if ($user === null) {
            throw new TokenAuthenticationException("No user matches the provided token");
        }
        return $user;
    }
}

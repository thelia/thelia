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

namespace Thelia\Core\Security\Authentication;

use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\Security\UserProvider\TokenUserProvider;

class TokenAuthenticator implements AuthenticatorInterface
{
    public function __construct(protected $key, protected TokenUserProvider $userProvider)
    {
    }

    /**
     * @see AuthenticatorInterface::getAuthentifiedUser()
     */
    public function getAuthentifiedUser(): ?UserInterface
    {
        $keyData = $this->userProvider->decodeKey($this->key);
        if (empty($keyData) || $keyData['username'] === '') {
            return null;
        }

        return $this->userProvider->getUser($keyData);
    }
}

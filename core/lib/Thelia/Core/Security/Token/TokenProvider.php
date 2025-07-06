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
namespace Thelia\Core\Security\Token;

use Thelia\Core\Security\User\UserInterface;

class TokenProvider
{
    public function encodeKey(UserInterface $user): string
    {
        // Always set a new token in the user environment
        $user->setToken(uniqid());

        return base64_encode(sprintf("%s\0%s\0%s", $user->getUsername(), $user->getToken(), $user->getSerial()));
    }

    public function decodeKey($key): array
    {
        $data = explode("\0", base64_decode((string) $key), 3);

        if (\count($data) !== 3) {
            $data = ['', '', ''];
        }

        return [
            'username' => $data[0],
            'token' => $data[1],
            'serial' => $data[2],
        ];
    }
}

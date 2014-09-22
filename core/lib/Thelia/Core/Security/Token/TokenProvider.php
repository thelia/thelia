<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Security\Token;

use Thelia\Core\Security\User\UserInterface;

class TokenProvider
{
    public function encodeKey(UserInterface $user)
    {
        // Always set a new token in the user environment
        $user->setToken(uniqid());

        return base64_encode(serialize(array($user->getUsername(), $user->getToken(), $user->getSerial())));
    }

    public function decodeKey($key)
    {
        $data = unserialize(base64_decode($key));

        return array(
            'username' => $data[0],
            'token'    => $data[1],
            'serial'   => $data[2]
        );
    }
}

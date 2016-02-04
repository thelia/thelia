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

namespace Thelia\Core\Security\UserProvider;

use Thelia\Core\Security\User\UserInterface;

interface UserProviderInterface
{
    /**
     * Returns a UserInterface instance
     *
     * @param string $key the unique user key (username, email address, etc.)
     * @return UserInterface instance, or null if none was found.
     */
    public function getUser($key);
}

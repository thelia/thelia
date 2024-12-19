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

interface AuthenticatorInterface
{
    /**
     * Returns a UserInterface instance, authentified using the authenticator specific method.
     */
    public function getAuthentifiedUser();
}

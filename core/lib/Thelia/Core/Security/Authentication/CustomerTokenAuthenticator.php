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

use Thelia\Core\Security\UserProvider\CustomerTokenUserProvider;

class CustomerTokenAuthenticator extends TokenAuthenticator
{
    public function __construct($key)
    {
        parent::__construct(
            $key,
            new CustomerTokenUserProvider()
        );
    }
}

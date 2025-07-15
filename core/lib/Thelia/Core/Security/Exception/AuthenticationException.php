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

namespace Thelia\Core\Security\Exception;

class AuthenticationException extends \Exception
{
    /** @var string The login template name */
    protected string $loginTemplate = 'login';

    /**
     * @return string the login template name
     */
    public function getLoginTemplate(): string
    {
        return $this->loginTemplate;
    }

    /**
     * Set the login template name.
     */
    public function setLoginTemplate(string $loginTemplate): void
    {
        $this->loginTemplate = $loginTemplate;
    }
}

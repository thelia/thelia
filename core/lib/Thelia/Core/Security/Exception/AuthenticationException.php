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

namespace Thelia\Core\Security\Exception;

class AuthenticationException extends \Exception
{
    /**
     * @var string The login template name
     */
    protected $loginTemplate = "login";

    /**
     * @return string the login template name
     */
    public function getLoginTemplate()
    {
        return $this->loginTemplate;
    }

    /**
     * Set the login template name
     *
     * @param string $loginPath the login template name
     */
    public function setLoginTemplate($loginTemplate)
    {
        $this->loginTemplate = $loginTemplate;
    }
}

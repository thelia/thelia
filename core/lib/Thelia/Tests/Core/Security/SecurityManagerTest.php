<?php

/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Security;

use Thelia\Core\Security\SecurityManager;
/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class SecurityManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSetToken()
    {
        /*
        $context = new SecurityManager($authProvider)(
            $this->getMock('AuthenticationProviderInterface'),
            $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')
        );
        $this->assertNull($context->getToken());

        $context->setToken($token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));
        $this->assertSame($token, $context->getToken());
        */
       // $this->assertFalse(1==1, "faux !");
    }
}

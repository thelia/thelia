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
namespace Thelia\Routing\Matcher;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Thelia\Controller\NullControllerInterface;

/**
 * Default matcher when no action is needed and there is no result for urlmatcher
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class DefaultMatcher implements RequestMatcherInterface
{
    protected $controller;

    public function __construct(NullControllerInterface $controller)
    {
        $this->controller = $controller;
    }

    public function matchRequest(Request $request)
    {
        $objectInformation = new \ReflectionObject($this->controller);

        $parameter = array(
          '_controller' => $objectInformation->getName().'::noAction'
        );

        return $parameter;
    }
}

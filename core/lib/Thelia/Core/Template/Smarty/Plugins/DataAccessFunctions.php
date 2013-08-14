<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
/**
 * Implementation of data access to main Thelia objects (users, cart, etc.)
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class DataAccessFunctions extends AbstractSmartyPlugin
{
    private $securityContext;
    protected $parserContext;

    public function __construct(SecurityContext $securityContext, ParserContext $parserContext)
    {
        $this->securityContext = $securityContext;
    }

    /**
     * Provides access to the current logged administrator attributes using the accessors.
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  the value of the requested attribute
     */
    public function adminDataAccess($params, &$smarty)
    {
         return $this->userDataAccess("Admin User", SecurityContext::CONTEXT_BACK_OFFICE, $params);
    }

     /**
      * Provides access to the current logged customer attributes throught the accessor
      *
      * @param  array $params
      * @param  unknown $smarty
      * @return string the value of the requested attribute
      */
     public function customerDataAccess($params, &$smarty)
     {
         return $this->userDataAccess("Customer User", SecurityContext::CONTEXT_FRONT_OFFICE, $params);
     }

    /**
     * Provides access to user attributes using the accessors.
     *
     * @param  array                    $params
     * @param  unknown                  $smarty
     * @return string                   the value of the requested attribute
     * @throws InvalidArgumentException if the object does not have the requested attribute.
     */
     protected function userDataAccess($objectLabel, $context, $params)
     {
         $attribute = $this->getNormalizedParam($params, array('attribute', 'attrib', 'attr'));

         if (! empty($attribute)) {
             $user = $this->securityContext->setContext($context)->getUser();

             if (null != $user) {
                 $getter = sprintf("get%s", ucfirst($attribute));

                 if (method_exists($user, $getter)) {
                     return $user->$getter();
                 }

                 throw new \InvalidArgumentException(sprintf("%s has no '%s' attribute", $objectLabel, $attribute));

             }
         }

         return '';
     }
    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'admin', $this, 'adminDataAccess'),
            new SmartyPluginDescriptor('function', 'customer', $this, 'customerDataAccess')
        );
    }
}

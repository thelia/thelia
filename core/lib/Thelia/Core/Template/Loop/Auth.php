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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Type\TypeCollection;
use Thelia\Type;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Auth extends BaseLoop
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
        	Argument::createAnyTypeArgument('roles', null, true),
        	Argument::createAnyTypeArgument('permissions')
         );
    }

    private function _explode($commaSeparatedValues)
    {

    	$array = explode(',', $commaSeparatedValues);

    	if (array_walk($array, function(&$item) {
    		$item = strtoupper(trim($item));
    	})) {
    		return $array;
    	}

    	return array();
    }

    /**
     *
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
    	$roles = $this->_explode($this->getRoles());
    	$permissions = $this->_explode($this->getPermissions());

    	$loopResult = new LoopResult();

    	try {
	    	$this->securityContext->isGranted($roles, $permissions == null ? array() : $permissions);

	    	// Create an empty row: loop is no longer empty :)
            $loopResult->addRow(new LoopResultRow());
    	}
    	catch (\Exception $ex) {
    		// Not granted, loop is empty
    	}

    	return $loopResult;
    }
}
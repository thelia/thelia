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

namespace Thelia\Core\Security;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ProfileResourceQuery;

/**
 * A simple security manager, in charge of checking user
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AccessManager
{
    const VIEW = 'VIEW';
    const CREATE = 'CREATE';
    const UPDATE = 'UPDATE';
    const DELETE = 'DELETE';

    protected $accessGranted = array(
        self::VIEW      =>  false,
        self::CREATE    =>  false,
        self::UPDATE    =>  false,
        self::DELETE    =>  false,
    );

    protected $accessPows = array(
        self::VIEW      =>  3,
        self::CREATE    =>  2,
        self::UPDATE    =>  1,
        self::DELETE    =>  0,
    );

    protected $accessValue;

    public function __construct($accessValue)
    {
        $this->accessValue = $accessValue;

        foreach($this->accessPows as $type => $value) {
            $pow = pow(2, $value);
            if($accessValue >= $pow) {
                $accessValue -= $pow;
                $this->accessGranted[$type] = true;
            } else {
                $this->accessGranted[$type] = false;
            }
        }
    }

    public function can($type)
    {
        if(!array_key_exists($type, $this->accessGranted)) {
            return false;
        }

        return $this->accessGranted[$type];

    }
}

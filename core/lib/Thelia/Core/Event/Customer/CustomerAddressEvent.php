<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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

namespace Thelia\Core\Event\Customer;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;


/**
 * Class CustomerAddressEvent
 * @package Thelia\Core\Event\Customer
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerAddressEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Address
     */
    protected $address;

    /**
     * @param Address $address
     */
    public function __construct(Address $address)
    {
        $this->address = $address;
    }

    /**
     * @param \Thelia\Model\Address $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return \Thelia\Model\Address
     */
    public function getAddress()
    {
        return $this->address;
    }

}
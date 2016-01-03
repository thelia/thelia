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

namespace Thelia\Core\Event\Address;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;

/**
 * Class AddressEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Address
     */
    protected $address;

    public function __construct(Address $address)
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

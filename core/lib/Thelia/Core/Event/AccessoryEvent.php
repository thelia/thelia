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

namespace Thelia\Core\Event;

use Thelia\Model\Accessory;

class AccessoryEvent extends ActionEvent
{
    public $accessory = null;

    public function __construct(Accessory $accessory = null)
    {
        $this->accessory = $accessory;
    }

    public function hasAccessory()
    {
        return ! is_null($this->accessory);
    }

    public function getAccessory()
    {
        return $this->accessory;
    }

    public function setAccessory(Accessory $accessory)
    {
        $this->accessory = $accessory;

        return $this;
    }
}

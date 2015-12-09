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


namespace Thelia\Core\Event\Cache;

use Thelia\Core\Event\TCacheEvent;

/**
 * Class TCacheDiscardKeyEvent
 * @package Thelia\Core\Event\Cache
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCacheDiscardKeyEvent extends TCacheEvent
{
    protected $key = null;

    /**
     * @param null $key
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return null
     */
    public function getKey()
    {
        return $this->key;
    }
}

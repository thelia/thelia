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
 * Class TCacheDiscardRef
 * @package Thelia\Core\Event\Cache
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCacheDiscardRefEvent extends TCacheEvent
{
    protected $ref = null;

    /**
     * @param null $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
        return $this;
    }

    /**
     * @return null
     */
    public function getRef()
    {
        return $this->ref;
    }
}

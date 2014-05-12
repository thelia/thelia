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

namespace Thelia\Core\Event\Hook;
use Thelia\Core\Event\UpdatePositionEvent;


/**
 * Class ModuleHookUpdatePositionEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookUpdatePositionEvent extends UpdatePositionEvent {

    /** @var String $hook  */
    protected $hook = null;

    /**
     * @param null $hook
     * @return String|null
     */
    public function setHook($hook)
    {
        $this->hook = $hook;
        return $this;
    }

    /**
     * @return String|null
     */
    public function getHook()
    {
        return $this->hook;
    }


} 
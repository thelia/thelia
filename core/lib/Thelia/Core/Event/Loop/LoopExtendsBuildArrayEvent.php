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

namespace Thelia\Core\Event\Loop;

use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsBuildArrayEvent
 *
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsBuildArrayEvent extends LoopExtendsEvent
{
    /**
     * @var array Build array results
     */
    protected $array;

    /**
     * Class constructor
     *
     * @param \Thelia\Core\Template\Element\BaseLoop $loop  Loop object
     * @param array                                  $array Build array base results
     */
    public function __construct(BaseLoop $loop, array $array)
    {
        parent::__construct($loop);

        $this->array = $array;
    }

    /**
     * Get build array results
     *
     * @return array Build array results
     */
    public function getArray()
    {
        return $this->array;
    }

    /**
     * Set build array results
     *
     * @param array $array
     *
     * @return $this Return $this, allow chaining
     */
    public function setArray(array $array)
    {
        $this->array = $array;

        return $this;
    }
}

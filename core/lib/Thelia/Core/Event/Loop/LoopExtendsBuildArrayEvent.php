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
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsBuildArrayEvent extends LoopExtendsEvent
{
    /** @var array $array */
    protected $array;

    /**
     * LoopExtendsBuildArrayEvent constructor.
     * @param array $array
     */
    public function __construct(BaseLoop $loop, array $array)
    {
        parent::__construct($loop);
        $this->array = $array;
    }

    /**
     * @return array
     */
    public function getArray()
    {
        return $this->array;
    }
}

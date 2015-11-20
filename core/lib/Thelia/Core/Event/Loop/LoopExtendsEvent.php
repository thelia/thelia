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

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsEvent
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsEvent extends ActionEvent
{
    /** @var BaseLoop|null $loop */
    protected $loop = null;

    /**
     * LoopExtendsEvent constructor.
     * @param null|BaseLoop $loop
     */
    public function __construct(BaseLoop $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Get the loop
     *
     * @return null|BaseLoop
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * Get the loop name
     *
     * @return null|string
     */
    public function getLoopName()
    {
        return $this->loop->getLoopName();
    }
}

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
use Thelia\Core\Template\Element\LoopResult;

/**
 * Class LoopExtendsParseResultsEvent
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsParseResultsEvent extends LoopExtendsEvent
{
    /** @var LoopResult $loopResult */
    protected $loopResult;

    /**
     * LoopExtendsParseResultsEvent constructor.
     * @param LoopResult $loopResult
     */
    public function __construct(BaseLoop $loop, LoopResult $loopResult)
    {
        parent::__construct($loop);
        $this->loopResult = $loopResult;
    }

    /**
     * @return LoopResult
     */
    public function getLoopResult()
    {
        return $this->loopResult;
    }
}

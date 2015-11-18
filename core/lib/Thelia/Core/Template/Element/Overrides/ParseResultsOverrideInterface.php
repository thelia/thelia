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


namespace Thelia\Core\Template\Element\Overrides;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

/**
 * Class ParseResultsOverrideInterface
 * @package Thelia\Core\Template\Element\Overrides
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ParseResultsOverrideInterface
{
    /**
     * Manipulate the parse results
     *
     * For instance:
     *
     * $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
     * $result->set('TEST', str_shuffle($characters));
     *
     *
     * @param BaseLoop        $loop    the current loop
     * @param LoopResultRow   $result  the current loop result row of the loop
     * @param object|array    $item
     *
     * @return LoopResult             the modified LoopResult
     */
    public function parseResults(BaseLoop $loop, LoopResultRow $result, $item);
}

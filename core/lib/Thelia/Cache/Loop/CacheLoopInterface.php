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

namespace Thelia\Cache\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;

/**
 * Class CacheLoopInterface
 * @package Thelia\Cache
 * @author Manuel Raynaud <manu@thelia.net>
 */
interface CacheLoopInterface
{
    /**
     * store loop result in cache. Witht the all object, each cache can defined its own key
     *
     * @param BaseLoop $loop
     * @param LoopResult $loopResult
     * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public function set(BaseLoop $loop, LoopResult $loopResult);

    /**
     * @param BaseLoop $loop
     * @return \Thelia\Core\Template\Element\LoopResult|false or false if no entry found
     */
    public function get(BaseLoop $loop);

    /**
     * @param BaseLoop $loop
     * @return boolean true is resource exists, false otherwise
     */
    public function has(BaseLoop $loop);
}

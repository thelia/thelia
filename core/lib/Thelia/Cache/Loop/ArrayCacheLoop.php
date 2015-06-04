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
 * Class ArrayCacheLoop
 * @package Thelia\Cache\Loop
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ArrayCacheLoop implements CacheLoopInterface
{

    /**
     * @var array
     */
    private $data = [];

    public function set(BaseLoop $loop, LoopResult $loopResult)
    {
        $key = $loop->getArgs()->getHash();
        $this->data[$key] = $loopResult;
    }

    public function get(BaseLoop $loop)
    {
        $key = $loop->getArgs()->getHash();

        return isset($this->data[$key]) ? $this->data[$key] : false;
    }

    public function has(BaseLoop $loop)
    {
        $key = $loop->getArgs()->getHash();

        return isset($this->data[$key]) ;
    }
}

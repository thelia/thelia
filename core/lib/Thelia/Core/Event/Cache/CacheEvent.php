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

use Thelia\Core\Event\ActionEvent;

/**
 * Class CacheEvent
 * @package Thelia\Core\Event\Cache
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CacheEvent extends ActionEvent
{
    /**
     * @var string cache directory
     */
    protected $dir;

    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @param mixed $dir
     *
     * @return $this
     */
    public function setDir($dir)
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDir()
    {
        return $this->dir;
    }
}

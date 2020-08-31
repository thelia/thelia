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
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class CacheEvent extends ActionEvent
{
    /**
     * @var string cache directory
     */
    protected $dir;

    /**
     * @since 2.4.0
     * @var bool
     */
    protected $onKernelTerminate = true;

    public function __construct($dir, $onKernelTerminate = true)
    {
        $this->dir = $dir;
        $this->onKernelTerminate = $onKernelTerminate;
    }

    /**
     * @param string $dir
     *
     * @return $this
     */
    public function setDir($dir)
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @since 2.4.0
     * @return bool
     */
    public function isOnKernelTerminate()
    {
        return $this->onKernelTerminate;
    }

    /**
     * @since 2.4.0
     * @param bool $onKernelTerminate
     * @return CacheEvent
     */
    public function setOnKernelTerminate($onKernelTerminate)
    {
        $this->onKernelTerminate = $onKernelTerminate;
        return $this;
    }
}

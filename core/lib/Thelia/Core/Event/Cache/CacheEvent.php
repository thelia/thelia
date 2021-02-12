<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Cache;

use Thelia\Core\Event\ActionEvent;

/**
 * Class CacheEvent.
 *
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
     *
     * @var bool
     */
    protected $onKernelTerminate = true;

    public function __construct(string $dir, bool $onKernelTerminate = true)
    {
        $this->dir = $dir;
        $this->onKernelTerminate = $onKernelTerminate;
    }

    public function setDir(string $dir): self
    {
        $this->dir = $dir;

        return $this;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @since 2.4.0
     */
    public function isOnKernelTerminate(): bool
    {
        return $this->onKernelTerminate;
    }

    /**
     * @since 2.4.0
     */
    public function setOnKernelTerminate(bool $onKernelTerminate): self
    {
        $this->onKernelTerminate = $onKernelTerminate;

        return $this;
    }
}

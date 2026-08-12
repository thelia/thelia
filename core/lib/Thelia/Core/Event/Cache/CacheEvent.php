<?php

declare(strict_types=1);

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
    public function __construct(
        /**
         * @var string cache directory
         */
        protected string $dir,
        protected bool $onKernelTerminate = true,
        /**
         * The combined Propel schema depends only on which modules are active and on
         * the content of their schema.xml. It is dropped by default, because most
         * clears follow a change that may have touched either; pass false when the
         * caller knows the database schema cannot have moved.
         */
        protected bool $invalidatePropelSchema = true,
    ) {
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

    public function isOnKernelTerminate(): bool
    {
        return $this->onKernelTerminate;
    }

    public function setOnKernelTerminate(bool $onKernelTerminate): self
    {
        $this->onKernelTerminate = $onKernelTerminate;

        return $this;
    }

    public function invalidatesPropelSchema(): bool
    {
        return $this->invalidatePropelSchema;
    }

    public function setInvalidatePropelSchema(bool $invalidatePropelSchema): self
    {
        $this->invalidatePropelSchema = $invalidatePropelSchema;

        return $this;
    }
}

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

namespace Thelia\Tests\Core\Event;

use Thelia\Core\Event\ActionEvent;

/**
 * Class FooEvent
 * @package Thelia\Tests\Core\Event
 * @author manuel raynaud <manu@raynaud.io>
 */
class FooEvent extends ActionEvent
{
    protected $foo;
    protected $bar;

    /**
     */
    public function getBar()
    {
        return $this->bar;
    }

    /**
     * @return $this
     */
    public function setBar($bar)
    {
        $this->bar = $bar;

        return $this;
    }

    /**
     */
    public function getFoo()
    {
        return $this->foo;
    }

    /**
     * @return $this
     */
    public function setFoo($foo)
    {
        $this->foo = $foo;

        return $this;
    }
}

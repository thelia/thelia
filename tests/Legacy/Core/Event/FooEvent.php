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
     * @return mixed
     */
    public function getBar()
    {
        return $this->bar;
    }

    /**
     * @param  mixed $bar
     * @return $this
     */
    public function setBar($bar)
    {
        $this->bar = $bar;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFoo()
    {
        return $this->foo;
    }

    /**
     * @param  mixed $foo
     * @return $this
     */
    public function setFoo($foo)
    {
        $this->foo = $foo;

        return $this;
    }
}

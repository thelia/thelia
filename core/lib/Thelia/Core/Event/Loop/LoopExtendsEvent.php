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

namespace Thelia\Core\Event\Loop;

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class LoopExtendsEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsEvent extends ActionEvent
{
    /** @var BaseLoop|null $loop */
    protected $loop;

    /**
     * LoopExtendsEvent constructor.
     *
     * @param BaseLoop|null $loop
     */
    public function __construct(BaseLoop $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Get the loop.
     *
     * @return BaseLoop|null
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * Get the loop name.
     *
     * @return string|null
     */
    public function getLoopName()
    {
        return $this->loop->getLoopName();
    }
}

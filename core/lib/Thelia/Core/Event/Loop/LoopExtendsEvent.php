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
    /**
     * LoopExtendsEvent constructor.
     *
     * @param BaseLoop|null $loop
     */
    public function __construct(protected BaseLoop $loop)
    {
    }

    /**
     * Get the loop.
     */
    public function getLoop(): BaseLoop
    {
        return $this->loop;
    }

    /**
     * Get the loop name.
     */
    public function getLoopName(): ?string
    {
        return $this->loop->getLoopName();
    }
}

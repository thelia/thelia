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

/**
 * Class LoopExtendsArgDefinitionsEvent
 * @package Thelia\Core\Event\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class LoopExtendsArgDefinitionsEvent extends LoopExtendsEvent
{
    public function getArgumentCollection()
    {
        return $this->loop->getArgumentCollection();
    }
}

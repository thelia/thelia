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

namespace Thelia\Core\Event\Hook;

/**
 * Class HookToggleNativeEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookToggleNativeEvent extends HookEvent
{
    public function __construct(protected $hook_id)
    {
    }

    public function setHookId($hook_id)
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    public function getHookId()
    {
        return $this->hook_id;
    }
}

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
namespace Thelia\Core\Event\Hook;

/**
 * Class HookDeleteEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookDeleteEvent extends HookEvent
{
    /**
     * @param int $hook_id
     */
    public function __construct(protected $hook_id)
    {
    }

    /**
     * @return $this
     */
    public function setHookId($hook_id): static
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getHookId()
    {
        return $this->hook_id;
    }
}

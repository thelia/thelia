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

namespace Thelia\Core\Event\State;

/**
 * Class StateUpdateEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateUpdateEvent extends StateCreateEvent
{
    /**
     * @param int $state_id
     */
    public function __construct(protected $state_id)
    {
    }

    /**
     * @return $this
     */
    public function setStateId(int $state_id): static
    {
        $this->state_id = $state_id;

        return $this;
    }

    public function getStateId(): int
    {
        return $this->state_id;
    }
}

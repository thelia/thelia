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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\State;

/**
 * Class StateEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\StateEvent
 */
class StateEvent extends ActionEvent
{
    public function __construct(protected ?State $state = null)
    {
    }

    /**
     * @param mixed $state
     */
    public function setState(State $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function hasState(): bool
    {
        return $this->state instanceof State;
    }
}

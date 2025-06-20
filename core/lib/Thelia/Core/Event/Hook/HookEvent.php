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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Hook;

/**
 * Class HookEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookEvent extends ActionEvent
{
    /**
     * @var Hook|null
     */
    public $hook;

    public function __construct(Hook $hook = null)
    {
        $this->hook = $hook;
    }

    public function hasHook(): bool
    {
        return null !== $this->hook;
    }

    public function getHook()
    {
        return $this->hook;
    }

    public function setHook(Hook $hook): static
    {
        $this->hook = $hook;

        return $this;
    }
}

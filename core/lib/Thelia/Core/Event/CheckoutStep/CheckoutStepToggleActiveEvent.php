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

namespace Thelia\Core\Event\CheckoutStep;

/**
 * Turns a step of the checkout on or off, which is how a shop stops showing a screen
 * without giving up the check made behind it when the order is placed.
 *
 * The step is named by its code and not by its id: that is what a theme, a module and
 * a route all know it by, and what survives a table rebuilt by a migration.
 *
 * The event carries the state to write and not the wish to flip whatever is there: two
 * administrators on the same screen would otherwise each read the row, each flip what
 * they read, and leave the step in the state neither of them clicked for.
 */
class CheckoutStepToggleActiveEvent extends CheckoutStepEvent
{
    public function __construct(protected string $code, protected bool $active)
    {
        parent::__construct();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * The state the step is to be left in, not the one it is in.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}

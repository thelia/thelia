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
 * Moves a step to a place in the tunnel, counting from 1.
 *
 * The generic UpdatePositionEvent is not used here: it moves a row up, down or to an
 * absolute place with no say over the result, and the checkout has an order it cannot
 * sell without. The whole list is judged after the move, so the event carries the place
 * asked for and the refusal comes back as an exception.
 */
class CheckoutStepUpdatePositionEvent extends CheckoutStepEvent
{
    public function __construct(
        protected string $code,
        protected int $position,
    ) {
        parent::__construct();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}

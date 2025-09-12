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

namespace Thelia\Core\Event;

class UpdatePositionEvent extends ActionEvent
{
    public const POSITION_UP = 1;
    public const POSITION_DOWN = 2;
    public const POSITION_ABSOLUTE = 3;

    /**
     * UpdatePositionEvent constructor.
     */
    public function __construct(
        protected ?int $objectId,
        protected int $mode,
        protected ?int $position = null,
        protected ?int $referrerId = null,
    ) {
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    public function setObjectId(?int $objectId = null): static
    {
        $this->objectId = $objectId;

        return $this;
    }

    public function getReferrerId(): ?int
    {
        return $this->referrerId;
    }

    public function setReferrerId(?int $referrerId): void
    {
        $this->referrerId = $referrerId;
    }
}

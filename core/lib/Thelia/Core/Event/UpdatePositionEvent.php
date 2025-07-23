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

    protected int $objectId;

    /** @deprecated since 2.3, will be removed in 2.5, because this variable is not used */
    protected $object;

    /**
     * UpdatePositionEvent constructor.
     *
     * @param int $objectId
     * @param int $mode
     */
    public function __construct(/**
     * @deprecated since 2.3, will be removed in 2.5, this variable has been replaced by $objectId
     */
        protected $object_id,
        protected $mode,
        protected $position = null,
        protected $referrerId = null,
    ) {
        $this->objectId = $this->object_id;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * @return $this
     */
    public function setMode(int $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    /**
     * @return $this
     */
    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    /**
     * @return $this
     */
    public function setObjectId(int $objectId): static
    {
        $this->object_id = $objectId;
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

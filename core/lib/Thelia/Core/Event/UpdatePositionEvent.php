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

namespace Thelia\Core\Event;

class UpdatePositionEvent extends ActionEvent
{
    public const POSITION_UP = 1;
    public const POSITION_DOWN = 2;
    public const POSITION_ABSOLUTE = 3;

    /**
     * @var int
     *
     * @deprecated since 2.3, will be removed in 2.5, this variable has been replaced by $objectId
     */
    protected $object_id;

    /**
     * @var int
     *
     * @since 2.3
     */
    protected $objectId;

    /**
     * @var int|null
     *
     * @since 2.3
     */
    protected $referrerId;

    /** @var int */
    protected $mode;

    /** @var int|null */
    protected $position;

    /**
     * @deprecated since 2.3, will be removed in 2.5, because this variable is not used
     */
    protected $object;

    /**
     * UpdatePositionEvent constructor.
     *
     * @param $objectId
     * @param $mode
     * @param null $position
     * @param null $referrerId
     */
    public function __construct($objectId, $mode, $position = null, $referrerId = null)
    {
        $this->object_id = $objectId;
        $this->objectId = $objectId;
        $this->mode = $mode;
        $this->position = $position;
        $this->referrerId = $referrerId;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param int $mode
     *
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     *
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return int
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @param int $objectId
     *
     * @return $this
     */
    public function setObjectId($objectId)
    {
        $this->object_id = $objectId;
        $this->objectId = $objectId;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getReferrerId()
    {
        return $this->referrerId;
    }

    /**
     * @param int|null $referrerId
     */
    public function setReferrerId($referrerId): void
    {
        $this->referrerId = $referrerId;
    }
}

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

namespace Thelia\Core\Event\MetaData;

/**
 * Class MetaDataCreateOrUpdateEvent.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataDeleteEvent extends MetaDataEvent
{
    /** @var string */
    protected $metaKey;

    /** @var string */
    protected $elementKey;

    /** @var int */
    protected $elementId;

    /**
     * MetaDataDeleteEvent constructor.
     *
     * @param string|null $metaKey
     * @param string|null $elementKey
     * @param int|null    $elementId
     */
    public function __construct($metaKey = null, $elementKey = null, $elementId = null)
    {
        parent::__construct();

        $this->metaKey = $metaKey;
        $this->elementKey = $elementKey;
        $this->elementId = $elementId;
    }

    /**
     * @param string $metaKey
     *
     * @return $this
     */
    public function setMetaKey($metaKey)
    {
        $this->metaKey = $metaKey;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getMetaKey()
    {
        return $this->metaKey;
    }

    /**
     * @param $elementKey
     *
     * @return $this
     */
    public function setElementKey($elementKey)
    {
        $this->elementKey = $elementKey;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getElementKey()
    {
        return $this->elementKey;
    }

    /**
     * @param int $elementId
     *
     * @return $this
     */
    public function setElementId($elementId)
    {
        $this->elementId = $elementId;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getElementId()
    {
        return $this->elementId;
    }
}

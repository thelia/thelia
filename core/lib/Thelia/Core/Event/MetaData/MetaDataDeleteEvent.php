<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\MetaData;

/**
 * Class MetaDataCreateOrUpdateEvent
 * @package Thelia\Core\Event\MetaData
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataDeleteEvent extends MetaDataEvent
{
    /** @var string */
    protected $metaKey = null;

    /** @var string */
    protected $elementKey = null;

    /** @var int */
    protected $elementId = null;

    /**
     * MetaDataDeleteEvent constructor.
     * @param string|null $metaKey
     * @param string|null $elementKey
     * @param int|null $elementId
     */
    public function __construct($metaKey = null, $elementKey = null, $elementId = null)
    {
        parent::__construct();

        $this->metaKey    = $metaKey;
        $this->elementKey = $elementKey;
        $this->elementId  = $elementId;
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
     * @return null|string
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

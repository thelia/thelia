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

namespace Thelia\Core\Event\Content;

/**
 * Class ContentUpdateEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentUpdateEvent extends ContentCreateEvent
{
    /** @var int */
    protected $content_id;

    protected $chapo;
    protected $description;
    protected $postscriptum;

    /**
     * @param int $content_id
     */
    public function __construct($content_id)
    {
        $this->content_id = $content_id;
    }

    /**
     * @return $this
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @return $this
     */
    public function setContentId($content_id)
    {
        $this->content_id = $content_id;

        return $this;
    }

    public function getContentId()
    {
        return $this->content_id;
    }

    /**
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return $this
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }
}

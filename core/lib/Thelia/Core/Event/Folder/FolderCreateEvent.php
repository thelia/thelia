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

namespace Thelia\Core\Event\Folder;

/**
 * Class FolderCreateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderCreateEvent extends FolderEvent
{
    protected $title;
    protected $parent;
    protected $locale;
    protected $visible;

    /**
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     *
     *
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     *
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     */
    public function getVisible()
    {
        return $this->visible;
    }
}

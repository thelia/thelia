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

namespace Thelia\Core\Event\Content;

/**
 * Class ContentCreateEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentCreateEvent extends ContentEvent
{
    protected $title;
    protected $default_folder;
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
     * @return $this
     */
    public function setDefaultFolder($default_folder)
    {
        $this->default_folder = $default_folder;

        return $this;
    }

    /**
     */
    public function getDefaultFolder()
    {
        return $this->default_folder;
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
}

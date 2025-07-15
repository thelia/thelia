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

namespace Thelia\Core\Event\Content;

/**
 * Class ContentCreateEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentCreateEvent extends ContentEvent
{
    protected $title;

    protected $default_folder;

    protected $locale;

    protected $visible;

    public function setLocale($locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setDefaultFolder($default_folder): self
    {
        $this->default_folder = $default_folder;

        return $this;
    }

    public function getDefaultFolder()
    {
        return $this->default_folder;
    }

    public function setVisible($visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setTitle($title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }
}

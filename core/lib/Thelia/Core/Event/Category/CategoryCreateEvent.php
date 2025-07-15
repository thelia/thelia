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

namespace Thelia\Core\Event\Category;

class CategoryCreateEvent extends CategoryEvent
{
    protected $title;

    protected $parent;

    protected $locale;

    protected $visible;

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent($parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }

    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }
}

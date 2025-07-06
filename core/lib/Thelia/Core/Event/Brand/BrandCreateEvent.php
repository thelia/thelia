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

namespace Thelia\Core\Event\Brand;

/**
 * Class BrandCreateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandCreateEvent extends BrandEvent
{
    protected $title;

    protected $locale;

    protected $visible;

    /**
     * @param string $locale
     *
     * @return BrandCreateEvent $this
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $title
     *
     * @return BrandCreateEvent $this
     */
    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param bool $visible
     *
     * @return BrandCreateEvent $this
     */
    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @return bool
     */
    public function getVisible()
    {
        return $this->visible;
    }
}

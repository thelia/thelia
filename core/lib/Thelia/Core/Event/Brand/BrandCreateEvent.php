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
     * @return BrandCreateEvent $this
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return BrandCreateEvent $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return BrandCreateEvent $this
     */
    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }
}

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

namespace Thelia\Core\Event\Sale;

/**
 * Class SaleCreateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleCreateEvent extends SaleEvent
{
    protected $title;
    protected $saleLabel;
    protected $locale;

    /**
     * @return SaleCreateEvent $this
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
     * @return $this
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
     * @return $this
     */
    public function setSaleLabel(string $saleLabel): static
    {
        $this->saleLabel = $saleLabel;

        return $this;
    }

    public function getSaleLabel(): string
    {
        return $this->saleLabel;
    }
}

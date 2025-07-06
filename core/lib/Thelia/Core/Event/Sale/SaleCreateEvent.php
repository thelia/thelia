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
     * @param string $locale
     *
     * @return SaleCreateEvent $this
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
     * @return $this
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
     * @param string $saleLabel
     *
     * @return $this
     */
    public function setSaleLabel($saleLabel): static
    {
        $this->saleLabel = $saleLabel;

        return $this;
    }

    /**
     * @return string
     */
    public function getSaleLabel()
    {
        return $this->saleLabel;
    }
}

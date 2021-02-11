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

namespace Thelia\Core\Event\Brand;

/**
 * Class BrandDeleteEvent
 * @package Thelia\Core\Event\Brand
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandDeleteEvent extends BrandEvent
{
    /** @var int */
    protected $brand_id;

    /**
     * @param int $brand_id
     */
    public function __construct($brand_id)
    {
        $this->brand_id = $brand_id;
    }

    /**
     * @param int $brand_id
     *
     * @return BrandDeleteEvent $this
     */
    public function setBrandId($brand_id)
    {
        $this->brand_id = $brand_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getBrandId()
    {
        return $this->brand_id;
    }
}

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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Brand;

/**
 * Class BrandEvent
 * @package Thelia\Core\Event\Brand
 * @author  Franck Allimant <franck@cqfdev.fr>
 * @deprecated since 2.4, please use \Thelia\Model\Event\BrandEvent
 */
class BrandEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Brand
     */
    protected $brand;

    public function __construct(Brand $brand = null)
    {
        $this->brand = $brand;
    }

    /**
     * @return BrandEvent
     */
    public function setBrand(Brand $brand)
    {
        $this->brand = $brand;

        return $this;
    }

    /**
     * @return \Thelia\Model\Brand
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * check if brand exists
     *
     * @return bool
     */
    public function hasBrand()
    {
        return null !== $this->brand;
    }
}

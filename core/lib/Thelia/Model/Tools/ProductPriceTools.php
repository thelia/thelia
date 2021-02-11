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

namespace Thelia\Model\Tools;

/**
 * Utility class used to store price and promo price for a carte item.
 *
 * Class ProductPriceTools
 * @package Thelia\Model\Tools
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ProductPriceTools
{
    /**
     * The value for the price field.
     *
     * @var        double
     */
    protected $price;

    /**
     * The value for the promoPrice field.
     *
     * @var        double
     */
    protected $promoPrice;

    public function __construct($price, $promoPrice)
    {
        $this->price = $price;
        $this->promoPrice = $promoPrice;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return float
     */
    public function getPromoPrice()
    {
        return $this->promoPrice;
    }
}

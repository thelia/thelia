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

namespace Thelia\Model\Tools;

/**
 * Utility class used to store price and promo price for a carte item.
 *
 * Class ProductPriceTools
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ProductPriceTools
{
    /**
     * @param float $price
     * @param float $promoPrice
     */
    public function __construct(
        /**
         * The value for the price field.
         */
        protected $price,
        /**
         * The value for the promoPrice field.
         */
        protected $promoPrice,
    ) {
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

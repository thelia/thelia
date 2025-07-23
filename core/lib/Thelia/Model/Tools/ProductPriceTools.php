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
    public function __construct(
        protected float $price,
        protected float $promoPrice,
    ) {
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getPromoPrice(): float
    {
        return $this->promoPrice;
    }
}

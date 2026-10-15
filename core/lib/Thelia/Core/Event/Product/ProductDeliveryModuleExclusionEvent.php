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

namespace Thelia\Core\Event\Product;

use Thelia\Model\Product;

/**
 * A carrier the product cannot travel with, added to or taken off its exclusions.
 */
class ProductDeliveryModuleExclusionEvent extends ProductEvent
{
    public function __construct(Product $product, private readonly int $moduleId)
    {
        parent::__construct($product);
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }
}

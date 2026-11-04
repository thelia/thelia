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

namespace Thelia\Domain\Order\DTO;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OrderTotals
{
    /**
     * @param string $goods    lines, taxed, before the discount and the postage
     * @param string $discount order discount, taxed
     * @param string $postage  postage, taxed
     * @param string $tax      total tax of the order, postage included
     * @param string $total    amount due
     */
    public function __construct(
        public string $goods,
        public string $discount,
        public string $postage,
        public string $tax,
        public string $total,
    ) {
    }
}

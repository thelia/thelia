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

namespace Thelia\Domain\Order\Edition;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the order should become, applied at once: its product lines, and optionally a new
 * order discount and a new postage, both including tax. The customer hears of it through
 * the actions the shop configured on editing an order (OrderStatusActionTrigger::EDIT).
 */
#[Exclude]
final readonly class OrderEdit
{
    /**
     * @param list<OrderEditLine> $lines
     */
    public function __construct(
        public array $lines,
        public ?string $discount = null,
        public ?string $postage = null,
    ) {
    }
}

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
final readonly class OrderEditPreview
{
    /**
     * @param array<string, mixed> $summary what the edit changed, as the history would record it
     */
    public function __construct(
        public OrderTotals $before,
        public OrderTotals $after,
        public array $summary,
    ) {
    }
}

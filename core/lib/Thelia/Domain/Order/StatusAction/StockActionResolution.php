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

namespace Thelia\Domain\Order\StatusAction;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the stock actions say about a status change: the decision of the first one that
 * applies, if any, and the payloads of the actions it had to refuse on the way.
 */
#[Exclude]
final readonly class StockActionResolution
{
    /**
     * @param list<RefusedStockPayload> $refusedPayloads
     */
    public function __construct(
        public ?StockOperationDecision $decision,
        public array $refusedPayloads = [],
    ) {
    }

    public static function none(): self
    {
        return new self(null);
    }
}

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

namespace Thelia\Domain\Catalog\Product\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class PSECreateDTO implements DTOEventActionInterface
{
    /**
     * @param array<int> $attributeAvIds List of AttributeAv IDs for combination
     */
    public function __construct(
        public int $currencyId,
        public array $attributeAvIds = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency_id' => $this->currencyId,
            'attribute_av_ids' => $this->attributeAvIds,
        ];
    }
}

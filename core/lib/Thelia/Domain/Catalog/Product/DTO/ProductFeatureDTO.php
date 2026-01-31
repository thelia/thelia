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

readonly class ProductFeatureDTO implements DTOEventActionInterface
{
    public function __construct(
        public int $featureId,
        public int|string $featureValue,
        public bool $isTextValue = false,
        public ?string $locale = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'feature_id' => $this->featureId,
            'feature_value' => $this->featureValue,
            'is_text_value' => $this->isTextValue,
            'locale' => $this->locale,
        ];
    }
}

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

namespace Thelia\Domain\Catalog\Currency\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class CurrencyCreateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $name,
        public string $code,
        public string $symbol,
        public string $locale,
        public float $rate = 1.0,
        public ?string $format = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'symbol' => $this->symbol,
            'locale' => $this->locale,
            'rate' => $this->rate,
            'format' => $this->format,
        ];
    }
}

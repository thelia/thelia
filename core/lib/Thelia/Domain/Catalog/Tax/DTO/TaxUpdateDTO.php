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

namespace Thelia\Domain\Catalog\Tax\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class TaxUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $title,
        public string $locale,
        public string $type,
        public ?string $description = null,
        public array $requirements = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'locale' => $this->locale,
            'type' => $this->type,
            'description' => $this->description,
            'requirements' => $this->requirements,
        ];
    }
}

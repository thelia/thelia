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

readonly class TaxRuleUpdateDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $title,
        public string $locale,
        public ?string $description = null,
        public array $countryList = [],
        public array $countryDeletedList = [],
        public array $taxList = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'locale' => $this->locale,
            'description' => $this->description,
            'country_list' => $this->countryList,
            'country_deleted_list' => $this->countryDeletedList,
            'tax_list' => $this->taxList,
        ];
    }
}

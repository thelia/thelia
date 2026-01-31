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

namespace Thelia\Domain\Addressing\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class AddressDTO implements DTOEventActionInterface
{
    public function __construct(
        public string $label,
        public string $firstname,
        public string $lastname,
        public string $address1,
        public string $zipcode,
        public string $city,
        public int $countryId,
        public ?int $titleId = null,
        public ?string $address2 = null,
        public ?string $address3 = null,
        public ?string $phone = null,
        public ?string $cellphone = null,
        public ?string $company = null,
        public ?int $stateId = null,
        public bool $isDefault = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'title' => $this->titleId,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'address3' => $this->address3,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'country' => $this->countryId,
            'state' => $this->stateId,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'company' => $this->company,
            'is_default' => $this->isDefault,
        ];
    }
}

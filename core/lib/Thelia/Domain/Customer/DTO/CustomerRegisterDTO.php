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

namespace Thelia\Domain\Customer\DTO;

use Thelia\Domain\Shared\Contract\DTOEventActionInterface;

readonly class CustomerRegisterDTO implements DTOEventActionInterface
{
    public function __construct(
        private ?int $id = null,
        private ?string $firstname = null,
        private ?string $lastname = null,
        private ?string $email = null,
        private ?string $password = null,
        private ?int $title = null,
        private ?int $langId = null,
        private ?string $sponsor = null,
        private ?string $ref = null,
        private ?float $discount = null,
        private bool $forceEmailUpdate = false,
        private bool $enabled = false,
        private bool $reseller = false,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getTitle(): ?int
    {
        return $this->title;
    }

    public function getLangId(): ?int
    {
        return $this->langId;
    }

    public function getSponsor(): ?string
    {
        return $this->sponsor;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function isForceEmailUpdate(): bool
    {
        return $this->forceEmailUpdate;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isReseller(): bool
    {
        return $this->reseller;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'password' => $this->password,
            'title' => $this->title,
            'langId' => $this->langId,
            'sponsor' => $this->sponsor,
            'ref' => $this->ref,
            'discount' => $this->discount,
            'forceEmailUpdate' => $this->forceEmailUpdate,
            'enabled' => $this->enabled,
            'reseller' => $this->reseller,
        ];
    }
}

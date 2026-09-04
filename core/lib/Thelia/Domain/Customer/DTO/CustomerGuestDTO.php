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

/**
 * What a visitor gives to order without creating an account.
 *
 * It carries no password on purpose: a guest has none, and the shop must not be able
 * to invent one for an identity nobody chose to protect. The password only appears
 * later, if the guest converts the account.
 */
final readonly class CustomerGuestDTO implements DTOEventActionInterface
{
    public function __construct(
        private ?string $email = null,
        private ?string $firstname = null,
        private ?string $lastname = null,
        private ?int $title = null,
        private ?int $langId = null,
    ) {
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function getTitle(): ?int
    {
        return $this->title;
    }

    public function getLangId(): ?int
    {
        return $this->langId;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'title' => $this->title,
            'langId' => $this->langId,
        ];
    }
}

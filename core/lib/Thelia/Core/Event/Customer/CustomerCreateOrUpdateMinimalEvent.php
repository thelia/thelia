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

namespace Thelia\Core\Event\Customer;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Customer;

class CustomerCreateOrUpdateMinimalEvent extends ActionEvent
{
    protected string $firstname;
    protected string $lastname;
    protected string $email;
    protected string $password;
    protected ?int $title = null;
    protected ?int $langId = null;
    protected ?string $sponsor = null;
    protected ?string $ref = null;
    protected ?float $discount = null;
    protected bool $forceEmailUpdate = false;
    protected bool $enabled = false;
    protected bool $reseller = false;

    protected ?Customer $customer = null;

    public function __construct(?Customer $customer = null)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getFirstname(): ?string
    {
        if (!isset($this->firstname)) {
            return null;
        }

        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        if (!isset($this->lastname)) {
            return null;
        }

        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEmail(): ?string
    {
        if (!isset($this->email)) {
            return null;
        }

        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        if (!isset($this->password)) {
            return null;
        }

        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getTitle(): ?int
    {
        return $this->title;
    }

    public function setTitle(?int $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLangId(): ?int
    {
        return $this->langId;
    }

    public function setLangId(?int $langId): self
    {
        $this->langId = $langId;

        return $this;
    }

    public function getSponsor(): ?string
    {
        return $this->sponsor;
    }

    public function setSponsor(?string $sponsor): self
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function isForceEmailUpdate(): bool
    {
        return $this->forceEmailUpdate;
    }

    public function setForceEmailUpdate(bool $forceEmailUpdate): self
    {
        $this->forceEmailUpdate = $forceEmailUpdate;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isReseller(): bool
    {
        return $this->reseller;
    }

    public function setReseller(bool $reseller): self
    {
        $this->reseller = $reseller;

        return $this;
    }
}

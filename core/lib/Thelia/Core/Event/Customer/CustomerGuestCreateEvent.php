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

/**
 * Asks for the customer row that carries an order placed without an account.
 *
 * There is deliberately no password setter here: bindArray() would find it, and a
 * missing password would reach the account as an empty string rather than as
 * nothing at all. A guest has no password, and only the conversion gives it one.
 *
 * The three identity fields are typed but left uninitialized, so that a value the
 * caller never gave stays absent instead of being cast to an empty string by
 * {@see ActionEvent::bindArray()}.
 */
class CustomerGuestCreateEvent extends ActionEvent
{
    protected string $firstname;
    protected string $lastname;
    protected string $email;
    protected ?int $title = null;
    protected ?int $langId = null;

    public function __construct(protected ?Customer $customer = null)
    {
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
}

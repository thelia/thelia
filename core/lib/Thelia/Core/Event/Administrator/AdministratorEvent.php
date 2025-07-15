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

namespace Thelia\Core\Event\Administrator;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Admin;

class AdministratorEvent extends ActionEvent
{
    protected $id;
    protected $firstname;
    protected $lastname;
    protected $login;
    protected $email;
    protected $password;
    protected $profile;
    protected $locale;

    public function __construct(protected ?Admin $administrator = null)
    {
    }

    public function hasAdministrator(): bool
    {
        return $this->administrator instanceof Admin;
    }

    public function getAdministrator(): ?Admin
    {
        return $this->administrator;
    }

    public function setAdministrator(Admin $administrator): static
    {
        $this->administrator = $administrator;

        return $this;
    }

    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setFirstname($firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setLastname($lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setLogin($login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setPassword($password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setProfile($profile): static
    {
        if (0 === $profile) {
            $profile = null;
        }

        $this->profile = $profile;

        return $this;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return $this
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}

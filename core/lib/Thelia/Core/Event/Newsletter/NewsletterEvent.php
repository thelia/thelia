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

namespace Thelia\Core\Event\Newsletter;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Newsletter;

/**
 * Class NewsletterEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class NewsletterEvent extends ActionEvent
{
    /** @var string email to save */
    protected string $id;

    /** @var string first name subscriber */
    protected string $firstname;

    /** @var string last name subscriber */
    protected string $lastname;

    protected Newsletter $newsletter;

    /**
     * @param string $email
     * @param string $locale
     */
    public function __construct(
        /**
         * @var string email to save
         */
        protected $email,
        /**
         * @var string current locale
         */
        protected $locale,
    ) {
    }

    public function setNewsletter(Newsletter $newsletter): static
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    public function getNewsletter(): Newsletter
    {
        return $this->newsletter;
    }

    /**
     * @return $this
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return $this
     */
    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * @return $this
     */
    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * @return $this
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }
}

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
    /**
     * @var string email to save
     */
    protected $id;

    /**
     * @var string first name subscriber
     */
    protected $firstname;

    /**
     * @var string last name subscriber
     */
    protected $lastname;

    /**
     * @var Newsletter
     */
    protected $newsletter;

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

    /**
     * @param Newsletter $newsletter
     */
    public function setNewsletter($newsletter): static
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    /**
     * @return Newsletter
     */
    public function getNewsletter()
    {
        return $this->newsletter;
    }

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail($email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $firstname
     *
     * @return $this
     */
    public function setFirstname($firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @param string $lastname
     *
     * @return $this
     */
    public function setLastname($lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $id
     */
    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}

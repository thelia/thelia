<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Newsletter;

use Thelia\Core\Event\ActionEvent;

/**
 * Class NewsletterEvent
 * @package Thelia\Core\Event\Newsletter
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class NewsletterEvent extends ActionEvent
{
    /**
     * @var string email to save
     */
    protected $id;

    /**
     * @var string email to save
     */
    protected $email;

    /**
     * @var string first name subscriber
     */
    protected $firstname;

    /**
     * @var string last name subscriber
     */
    protected $lastname;

    /**
     * @var string current locale
     */
    protected $locale;

    /**
     * @var \Thelia\Model\Newsletter
     */
    protected $newsletter;

    public function __construct($email, $locale)
    {
        $this->email = $email;
        $this->locale = $locale;
    }

    /**
     * @param \Thelia\Model\Newsletter $newsletter
     */
    public function setNewsletter($newsletter)
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    /**
     * @return \Thelia\Model\Newsletter
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
    public function setEmail($email)
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
    public function setFirstname($firstname)
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
    public function setLastname($lastname)
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
    public function setLocale($locale)
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
    public function setId($id)
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

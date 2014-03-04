<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Newsletter;
use Thelia\Core\Event\ActionEvent;

/**
 * Class NewsletterEvent
 * @package Thelia\Core\Event\Newsletter
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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

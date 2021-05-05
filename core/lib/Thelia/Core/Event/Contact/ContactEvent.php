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

namespace Thelia\Core\Event\Contact;

use Symfony\Component\Form\Form;
use Thelia\Core\Event\ActionEvent;

/**
 * Class ContactController
 * @package Thelia\Controller\Front
 * @author Vincent Lopes-Vicente <vlopesvicente@gmail.com>
 * @since 2.4
 */
class ContactEvent extends ActionEvent
{
    /** @var Form */
    protected $form;

    /** @var string */
    protected $subject;

    /** @var string */
    protected $message;

    /** @var string */
    protected $email;

    /** @var string */
    protected $fullname;

    public function __construct(Form $form)
    {
        $this->form = $form;

        $this->subject = $form->get('subject')->getData();
        $this->message = $form->get('message')->getData();
        $this->email = $form->get('email')->getData();
        $this->fullname = $form->get('fullname')->getData();
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return ContactEvent
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @return ContactEvent
     */
    public function setMessage($message)
    {
        $this->message = $message;
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
     * @param string $email
     * @return ContactEvent
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getFullname()
    {
        return $this->fullname;
    }

    /**
     * @param string $fullname
     * @return ContactEvent
     */
    public function setFullname($fullname)
    {
        $this->fullname = $fullname;
        return $this;
    }

}
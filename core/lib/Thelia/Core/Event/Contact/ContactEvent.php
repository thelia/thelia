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

namespace Thelia\Core\Event\Contact;

use Symfony\Component\Form\Form;
use Thelia\Core\Event\ActionEvent;

/**
 * Class ContactController.
 *
 * @author Vincent Lopes-Vicente <vlopesvicente@gmail.com>
 *
 * @since 2.4
 */
class ContactEvent extends ActionEvent
{
    /** @var string */
    protected $subject;

    /** @var string */
    protected $message;

    /** @var string */
    protected $email;

    /** @var string */
    protected $name;

    public function __construct(protected Form $form)
    {
        $this->subject = $this->form->get('subject')->getData();
        $this->message = $this->form->get('message')->getData();
        $this->email = $this->form->get('email')->getData();
        $this->name = $this->form->get('name')->getData();
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
     */
    public function setSubject($subject): static
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
     */
    public function setMessage($message): static
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
     */
    public function setEmail($email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name): static
    {
        $this->name = $name;

        return $this;
    }
}

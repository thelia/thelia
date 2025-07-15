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
 */
class ContactEvent extends ActionEvent
{
    protected string $subject;
    protected string $message;
    protected string $email;
    protected string $name;

    public function __construct(protected Form $form)
    {
        $this->subject = $this->form->get('subject')->getData();
        $this->message = $this->form->get('message')->getData();
        $this->email = $this->form->get('email')->getData();
        $this->name = $this->form->get('name')->getData();
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}

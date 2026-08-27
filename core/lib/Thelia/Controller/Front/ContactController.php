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

namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Contact\ContactEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;

/**
 * Receives the contact form a front-office theme renders on its contact page.
 *
 * The theme owns the page and the two views; the core owns the form, the event and the
 * route that carries the submission, so that a theme gets a working contact page from
 * its markup alone. The message itself is sent by a listener of CONTACT_SUBMIT.
 */
class ContactController extends BaseFrontController
{
    /**
     * The view a theme is expected to name its "message sent" page after. A theme that
     * would rather show another page sets the form's success_url, which wins over it.
     */
    public const SUCCESS_VIEW = 'contact-success';

    public function send(EventDispatcherInterface $eventDispatcher): Response
    {
        $contactForm = $this->createForm(FrontForm::CONTACT);

        try {
            $form = $this->validateForm($contactForm, 'POST');

            $eventDispatcher->dispatch(new ContactEvent($form), TheliaEvents::CONTACT_SUBMIT);

            return $this->generateRedirect($contactForm->getSuccessUrl('/'.self::SUCCESS_VIEW));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'contact message submission',
                $exception->getMessage(),
                $contactForm,
                $exception,
            );
        }

        if ($contactForm->hasErrorUrl()) {
            return $this->generateRedirect($contactForm->getErrorUrl());
        }

        // Render the contact page again rather than redirect to it: the errors the parser
        // context now carries are read from the form on this very request.
        return $this->render('contact');
    }
}

<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Front\Controller;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Contact\ContactEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;

/**
 * Class ContactController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContactController extends BaseFrontController
{
    /**
     * send contact message.
     */
    public function sendAction(EventDispatcherInterface $eventDispatcher, MailerFactory $mailer)
    {
        $contactForm = $this->createForm(FrontForm::CONTACT);

        try {
            $form = $this->validateForm($contactForm);

            $event = new ContactEvent($form);

            $eventDispatcher->dispatch($event, TheliaEvents::CONTACT_SUBMIT);

            $mailer->sendSimpleEmailMessage(
                [ConfigQuery::getStoreEmail() => $event->getName()],
                [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
                $event->getSubject(),
                '',
                $event->getMessage(),
                [],
                [],
                [$event->getEmail() => $event->getName()]
            );

            if ($contactForm->hasSuccessUrl()) {
                return $this->generateSuccessRedirect($contactForm);
            }

            return $this->generateRedirectFromRoute('contact.success');
        } catch (FormValidationException $e) {
            $error_message = $e->getMessage();
        }

        Tlog::getInstance()->error(sprintf('Error during sending contact mail : %s', $error_message));

        $contactForm->setErrorMessage($error_message);

        $this->getParserContext()
            ->addForm($contactForm)
            ->setGeneralError($error_message)
        ;

        // Redirect to error URL if defined
        if ($contactForm->hasErrorUrl()) {
            return $this->generateErrorRedirect($contactForm);
        }
    }
}

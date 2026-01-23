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
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;

/**
 * Class ContactController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Loïc Mo <lmo@openstudio.fr>
 */
class ContactController extends BaseFrontController
{
    /**
     * Send contact message.
     */
    public function sendAction(EventDispatcherInterface $eventDispatcher, MailerFactory $mailer, ParserContext $parserContext)
    {
        $translator = Translator::getInstance();
        $contactForm = $this->createForm(FrontForm::CONTACT);

        try {
            $form = $this->validateForm($contactForm);
            $event = new ContactEvent($form);
            $eventDispatcher->dispatch($event, TheliaEvents::CONTACT_SUBMIT);

            $name = $translator?->trans('Sender name: %name%', ['%name%' => $event->getName()]);
            $email = $translator?->trans('Sender\'s e-mail address: %email%', ['%email%' => $event->getEmail()]);
            $message = $translator?->trans('Message content: %message%', ['%message%' => $event->getMessage()]);

            $locale = $this->getRequest()->getSession()->getLang()->getLocale();

            $messageContent =
                "<p>$name</p>\n".
                "<p>$email</p>\n".
                "<p>$message</p>";

            $mailer->sendSimpleEmailMessage(
                [ConfigQuery::getStoreEmail($locale) => $event->getName()],
                [ConfigQuery::getStoreEmail($locale) => ConfigQuery::getStoreName($locale)],
                $event->getSubject(),
                $messageContent,
                strip_tags($messageContent),
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

        $parserContext
            ->addForm($contactForm)
            ->setGeneralError($error_message)
        ;

        // Redirect to error URL if defined
        if ($contactForm->hasErrorUrl()) {
            return $this->generateErrorRedirect($contactForm);
        }
    }
}

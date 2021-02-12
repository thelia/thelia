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

use Front\Front;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Log\Tlog;
use Thelia\Model\Customer;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>, Franck Allimant <franck@cqfdev.fr>
 */
class NewsletterController extends BaseFrontController
{
    /**
     * @since 2.3.0-alpha2
     */
    public function unsubscribeAction()
    {
        $errorMessage = false;

        $newsletterForm = $this->createForm(FrontForm::NEWSLETTER_UNSUBSCRIBE);

        try {
            $form = $this->validateForm($newsletterForm);

            $email = $form->get('email')->getData();

            if (null !== $newsletter = NewsletterQuery::create()->findOneByEmail($email)) {
                $event = new NewsletterEvent(
                    $email,
                    $this->getRequest()->getSession()->getLang()->getLocale()
                );

                $event->setId($newsletter->getId());

                $this->dispatch(TheliaEvents::NEWSLETTER_UNSUBSCRIBE, $event);

                // If a success URL is defined in the form, redirect to it, otherwise use the defaut view
                if ($newsletterForm->hasSuccessUrl() && !$this->getRequest()->isXmlHttpRequest()) {
                    return $this->generateSuccessRedirect($newsletterForm);
                }
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            Tlog::getInstance()->error(sprintf('Error during newsletter unsubscription : %s', $errorMessage));

            $newsletterForm->setErrorMessage($errorMessage);
        }

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => ($errorMessage) ? false : true,
                'message' => ($errorMessage) ? $errorMessage : $this->getTranslator()->trans(
                    'Your subscription to our newsletter has been canceled.',
                    [],
                    Front::MESSAGE_DOMAIN
                ),
            ], ($errorMessage) ? 500 : 200);
        }

        $this->getParserContext()
            ->setGeneralError($errorMessage)
            ->addForm($newsletterForm);

        // If an error URL is defined in the form, redirect to it, otherwise use the defaut view
        if ($errorMessage && $newsletterForm->hasErrorUrl()) {
            return $this->generateErrorRedirect($newsletterForm);
        }
    }

    public function subscribeAction()
    {
        $errorMessage = false;

        $newsletterForm = $this->createForm(FrontForm::NEWSLETTER);

        try {
            $form = $this->validateForm($newsletterForm);

            $event = new NewsletterEvent(
                $form->get('email')->getData(),
                $this->getRequest()->getSession()->getLang()->getLocale()
            );

            /** @var Customer $customer */
            if (null !== $customer = $this->getSecurityContext()->getCustomerUser()) {
                $event
                    ->setFirstname($customer->getFirstname())
                    ->setLastname($customer->getLastname())
                ;
            } else {
                $event
                    ->setFirstname($form->get('firstname')->getData())
                    ->setLastname($form->get('lastname')->getData())
                ;
            }

            $this->dispatch(TheliaEvents::NEWSLETTER_SUBSCRIBE, $event);

            // If a success URL is defined in the form, redirect to it, otherwise use the defaut view
            if ($newsletterForm->hasSuccessUrl() && !$this->getRequest()->isXmlHttpRequest()) {
                return $this->generateSuccessRedirect($newsletterForm);
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            Tlog::getInstance()->error(sprintf('Error during newsletter subscription : %s', $errorMessage));

            $newsletterForm->setErrorMessage($errorMessage);
        }

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => ($errorMessage) ? false : true,
                'message' => ($errorMessage) ? $errorMessage : $this->getTranslator()->trans(
                    "Thanks for signing up! We'll keep you posted whenever we have any new updates.",
                    [],
                    Front::MESSAGE_DOMAIN
                ),
            ], ($errorMessage) ? 500 : 200);
        }

        $this->getParserContext()
            ->setGeneralError($errorMessage)
            ->addForm($newsletterForm);

        // If an error URL is defined in the form, redirect to it, otherwise use the defaut view
        if ($errorMessage && $newsletterForm->hasErrorUrl()) {
            return $this->generateErrorRedirect($newsletterForm);
        }
    }
}

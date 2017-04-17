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
 * Class NewsletterController
 * @package Thelia\Controller\Front
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
                "success" => ($errorMessage) ? false : true,
                "message" => ($errorMessage) ? $errorMessage : $this->getTranslator()->trans(
                    "Your subscription to our newsletter has been canceled.",
                    [],
                    Front::MESSAGE_DOMAIN
                )
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
            if ($newsletterForm->hasSuccessUrl() && ! $this->getRequest()->isXmlHttpRequest()) {
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
                "success" => ($errorMessage) ? false : true,
                "message" => ($errorMessage) ? $errorMessage : $this->getTranslator()->trans(
                    "Thanks for signing up! We'll keep you posted whenever we have any new updates.",
                    [],
                    Front::MESSAGE_DOMAIN
                )
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

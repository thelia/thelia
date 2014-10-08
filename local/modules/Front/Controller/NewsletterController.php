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

use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\NewsletterForm;

/**
 * Class NewsletterController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class NewsletterController extends BaseFrontController
{

    public function subscribeAction()
    {
        $error_message = false;
        $newsletterForm = new NewsletterForm($this->getRequest());

        try {

            $form = $this->validateForm($newsletterForm);

            $event = new NewsletterEvent(
                $form->get('email')->getData(),
                $this->getRequest()->getSession()->getLang()->getLocale()
            );

            if (null !== $customer = $this->getSecurityContext()->getCustomerUser()) {
                $event->setFirstname($customer->getFirstname());
                $event->setLastname($customer->getLastname());
            } else {
                $event->setFirstname($form->get('firstname')->getData());
                $event->setLastname($form->get('lastname')->getData());
            }

            $this->dispatch(TheliaEvents::NEWSLETTER_SUBSCRIBE, $event);

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        \Thelia\Log\Tlog::getInstance()->error(sprintf('Error during newsletter subscription : %s', $error_message));

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($error_message) {
                $response = $this->jsonResponse(json_encode(array(
                            "success" => false,
                            "message" => $error_message
                        )));
            } else {
                $response = $this->jsonResponse(json_encode(array(
                            "success" => true,
                            "message" => $this->getTranslator()->trans("Thanks for signing up! We'll keep you posted whenever we have any new updates.")
                        )));
            }

            return $response;

        } else {
            $newsletterForm->setErrorMessage($error_message);

            $this->getParserContext()
                ->addForm($newsletterForm)
                ->setGeneralError($error_message)
            ;
        }

    }
}

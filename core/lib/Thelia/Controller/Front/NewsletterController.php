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

namespace Thelia\Controller\Front;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Exception\FormValidationException;
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

            $event = new NewsletterEvent($form->get('email')->getData());

            if (null !== $customer = $this->getSecurityContext()->getCustomerUser())
            {
                $event->setFirstname($customer->getFirstname());
                $event->setLastname($customer->getLastname());
            }

            $this->dispatch(TheliaEvents::NEWSLETTER_SUBSCRIBE, $event);

        } catch(FormValidationException $e) {
            $error_message = $e->getMessage();
        }

        if($error_message) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf('Error during sending contact mail : %s', $error_message));

            $newsletterForm->setErrorMessage($error_message);

            $this->getParserContext()
                ->addForm($newsletterForm)
                ->setGeneralError($error_message)
            ;
        } else {
            $this->redirectToRoute('newsletter.success');
        }
    }
}
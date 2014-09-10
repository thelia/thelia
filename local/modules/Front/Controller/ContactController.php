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
use Thelia\Form\ContactForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;

/**
 * Class ContactController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ContactController extends BaseFrontController
{
    /**
     * send contact message
     */
    public function sendAction()
    {
        $error_message = false;
        $contactForm = new ContactForm($this->getRequest());

        try {
            $form = $this->validateForm($contactForm);

            $message = \Swift_Message::newInstance($form->get('subject')->getData())
                ->addFrom(ConfigQuery::getStoreEmail(), $form->get('name')->getData())
                ->addReplyTo($form->get('email')->getData(), $form->get('name')->getData())
                ->addTo(ConfigQuery::getStoreEmail(), ConfigQuery::getStoreName())
                ->setBody($form->get('message')->getData())
            ;

            $this->getMailer()->send($message);

        } catch (FormValidationException $e) {
            $error_message = $e->getMessage();
        }

        if ($error_message !== false) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf('Error during sending contact mail : %s', $error_message));

            $contactForm->setErrorMessage($error_message);

            $this->getParserContext()
                ->addForm($contactForm)
                ->setGeneralError($error_message)
            ;
        } else {
            return $this->generateRedirectFromRoute('contact.success');
        }
    }
}

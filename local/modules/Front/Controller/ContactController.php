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
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class ContactController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContactController extends BaseFrontController
{
    /**
     * send contact message
     */
    public function sendAction()
    {
        $contactForm = $this->createForm(FrontForm::CONTACT);
        
        try {
            $form = $this->validateForm($contactForm);
            
            $this->getMailer()->sendSimpleEmailMessage(
                [ ConfigQuery::getStoreEmail() => $form->get('name')->getData() ],
                [ ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName() ],
                $form->get('subject')->getData(),
                '',
                $form->get('message')->getData(),
                [],
                [],
                [ $form->get('email')->getData() => $form->get('name')->getData() ]
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

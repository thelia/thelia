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
namespace Thelia\Action;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Form\CategoryDeletionForm;
use Thelia\Form\BaseForm;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Action\Exception\FormValidationException;
use Thelia\Core\Event\ActionEvent;

abstract class BaseAction
{
	protected function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
	{
    	$form = $aBaseForm->getForm();

    	if ($aBaseForm->getRequest()->isMethod($expectedMethod)) {

    		$form->bind($aBaseForm->getRequest());

    		if ($form->isValid()) {

    			return $form;
    		}
            else {
              	throw new FormValidationException("Missing or invalid data");
            }
        }
        else {
        	throw new FormValidationException(sprintf("Wrong form method, %s expected.", $expectedMethod));
        }
	}

	/**
	 *
	 * @param BaseForm $aBaseForm
	 * @param string $error_message
	 * @param ActionEvent $event
	 */
	protected function propagateFormError(BaseForm $aBaseForm, $error_message, ActionEvent $event) {

        // The form has an error
        $aBaseForm->setError(true);
        $aBaseForm->setErrorMessage($error_message);

        // Store the form in the parser context
        $event->setErrorForm($aBaseForm);

        // Stop event propagation
        $event->stopPropagation();
	}

    protected function redirect($url, $status = 302)
    {
        $response = new RedirectResponse($url, $status);

        $response->send();
        exit;
    }

}
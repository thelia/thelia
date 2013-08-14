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

use Thelia\Form\BaseForm;
use Thelia\Action\Exception\FormValidationException;
use Thelia\Core\Event\ActionEvent;
use Symfony\Component\Form\Form;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseAction
{

    /**
     * @var The container
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Validate a BaseForm
     *
     * @param  BaseForm                     $aBaseForm      the form
     * @param  string                       $expectedMethod the expected method, POST or GET, or null for any of them
     * @throws FormValidationException      is the form contains error, or the method is not the right one
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    protected function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
    {
        $form = $aBaseForm->getForm();

        if ($expectedMethod == null || $aBaseForm->getRequest()->isMethod($expectedMethod)) {

            $form->bind($aBaseForm->getRequest());

            if ($form->isValid()) {
                return $form;
            } else {
                throw new FormValidationException("Missing or invalid data");
            }
        } else {
            throw new FormValidationException(sprintf("Wrong form method, %s expected.", $expectedMethod));
        }
    }

    /**
     * Propagate a form error in the action event
     *
     * @param BaseForm    $aBaseForm     the form
     * @param string      $error_message an error message that may be displayed to the customer
     * @param ActionEvent $event         the action event
     */
    protected function propagateFormError(BaseForm $aBaseForm, $error_message, ActionEvent $event)
    {
        // The form has an error
        $aBaseForm->setError(true);
        $aBaseForm->setErrorMessage($error_message);

        // Store the form in the parser context
        $event->setErrorForm($aBaseForm);

        // Stop event propagation
        $event->stopPropagation();
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

}

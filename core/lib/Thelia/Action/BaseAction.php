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
use Symfony\Component\Form\Form;
use Symfony\Component\DependencyInjection\ContainerAware;
use Thelia\Core\Template\ParserContext;
use Thelia\Log\Tlog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\Exception\AuthorizationException;

class BaseAction
{
    /**
     * @var The container
     */
    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Validate a BaseForm
     *
     * @param BaseForm $aBaseForm the form
     * @param string $expectedMethod the expected method, POST or GET, or null for any of them
     * @throws FormValidationException is the form contains error, or the method is not the right one
     * @return Symfony\Component\Form\Form Form the symfony form object
     */
    protected function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
    {
        $form = $aBaseForm->getForm();

        if ($expectedMethod == null || $aBaseForm->getRequest()->isMethod($expectedMethod)) {

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
     * Propagate a form error in the action event
     *
     * @param BaseForm $aBaseForm the form
     * @param string $error_message an error message that may be displayed to the customer
     * @param ActionEvent $event the action event
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

    /**
     * Check current user authorisations.
     *
     * @param mixed $roles a single role or an array of roles.
     * @param mixed $permissions a single permission or an array of permissions.
     *
     * @throws AuthenticationException if permissions are not granted to the current user.
     */
    protected function checkAuth($roles, $permissions, $context = false) {

        if (! $this->getSecurityContext($context)->isGranted(
            is_array($roles) ? $roles : array($roles),
            is_array($permissions) ? $permissions : array($permissions)) ) {

            Tlog::getInstance()->addAlert("Authorization roles:", $roles, " permissions:", $permissions, " refused.");

            throw new AuthorizationException("Sorry, you're not allowed to perform this action");
        }
    }

    /**
     * Return the event dispatcher,
     *
     * @return ParserContext
     */
    protected function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * Return the parser context,
     *
     * @return ParserContext
     */
    protected function getParserContext()
    {
        return $this->container->get('thelia.parser.context');
    }

    /**
     * Return the security context, by default in admin mode.
     *
     * @param string the context, either SecurityContext::CONTEXT_BACK_OFFICE or SecurityContext::CONTEXT_FRONT_OFFICE
     *
     * @return Thelia\Core\Security\SecurityContext
     */
    protected function getSecurityContext($context = false)
    {
        $securityContext = $this->container->get('thelia.securityContext');

        $securityContext->setContext($context === false ? SecurityContext::CONTEXT_BACK_OFFICE : $context);

        return $securityContext;
    }

    protected function redirect($url, $status = 302)
    {
        $response = new RedirectResponse($url, $status);

        $response->send();
        exit;
    }
}
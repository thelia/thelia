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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Security\AccessManager;

use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\SeoForm;

/**
 * Extend abstract CRUD controller to manage basic CRUD + SEO operations on a given object.
 *
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
abstract class AbstractSeoCrudController extends AbstractCrudController
{
    // Events
    protected $updateSeoEventIdentifier;

    /**
     * @param string $objectName the lower case object name. Example. "message"
     *
     * @param string $defaultListOrder          the default object list order, or null if list is not sortable. Example: manual
     * @param string $orderRequestParameterName Name of the request parameter that set the list order (null if list is not sortable)
     *
     * @param string $resourceCode the 'resource' code. Example: "admin.configuration.message"
     *
     * @param string $createEventIdentifier the dispatched create TheliaEvent identifier. Example: TheliaEvents::MESSAGE_CREATE
     * @param string $updateEventIdentifier the dispatched update TheliaEvent identifier. Example: TheliaEvents::MESSAGE_UPDATE
     * @param string $deleteEventIdentifier the dispatched delete TheliaEvent identifier. Example: TheliaEvents::MESSAGE_DELETE
     *
     * @param string $visibilityToggleEventIdentifier the dispatched visibility toggle TheliaEvent identifier, or null if the object has no visible options. Example: TheliaEvents::MESSAGE_TOGGLE_VISIBILITY
     * @param string $changePositionEventIdentifier   the dispatched position change TheliaEvent identifier, or null if the object has no position. Example: TheliaEvents::MESSAGE_UPDATE_POSITION
     * @param string $updateSeoEventIdentifier   the dispatched update SEO change TheliaEvent identifier, or null if the object has no SEO. Example: TheliaEvents::MESSAGE_UPDATE_SEO
    */
    public function __construct(
        $objectName,

        $defaultListOrder = null,
        $orderRequestParameterName = null,

        $resourceCode,

        $createEventIdentifier,
        $updateEventIdentifier,
        $deleteEventIdentifier,
        $visibilityToggleEventIdentifier = null,
        $changePositionEventIdentifier = null,
        $updateSeoEventIdentifier = null
    )
    {
        parent::__construct(
            $objectName,
            $defaultListOrder,
            $orderRequestParameterName,
            $resourceCode,
            $createEventIdentifier,
            $updateEventIdentifier,
            $deleteEventIdentifier,
            $visibilityToggleEventIdentifier,
            $changePositionEventIdentifier
        );

        $this->updateSeoEventIdentifier = $updateSeoEventIdentifier;

    }

    /**
     * Put in this method post object update SEO processing if required.
     *
     * @param  unknown  $updateSeoEvent the update event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalUpdateSeoAction($updateSeoEvent)
    {
        return null;
    }

    /**
     * Return the update SEO form for this object
     */
    protected function getUpdateSeoForm()
    {
        return new SeoForm($this->getRequest());
    }

    /**
     * Creates the update SEO event with the provided form data
     *
     * @param $formData
     * @return UpdateSeoEvent
     */
    protected function getUpdateSeoEvent($formData)
    {

        $updateSeoEvent = new UpdateSeoEvent($formData['id']);

        $updateSeoEvent
            ->setLocale($formData['locale'])
            ->setMetaTitle($formData['meta_title'])
            ->setMetaDescription($formData['meta_description'])
            ->setMetaKeyword($formData['meta_keyword'])
        ;

        // Create and dispatch the change event
        return $updateSeoEvent;
    }

    /**
     * Update SEO modification, and either go back to the object list, or stay on the edition page.
     *
     * @return Thelia\Core\HttpFoundation\Response the response
     */
    public function processUpdateSeoAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        // Create the form from the request
        $updateSeoForm = $this->getUpdateSeoForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($updateSeoForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $updateSeoEvent = $this->getUpdateSeoEvent($data);

            $this->dispatch($this->updateSeoEventIdentifier, $updateSeoEvent);

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            return $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }


        $response = $this->performAdditionalUpdateSeoAction($updateSeoEvent);

        if ($response == null) {
            // If we have to stay on the same page, do not redirect to the successUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToEditionTemplate($this->getRequest());
            }

            // Redirect to the success URL
            $this->redirect($updateSeoForm->getSuccessUrl());
        } else {
            return $response;
        }

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }
}

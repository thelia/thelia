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

use Thelia\Core\Event\TemplateDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TemplateUpdateEvent;
use Thelia\Core\Event\TemplateCreateEvent;
use Thelia\Model\TemplateQuery;
use Thelia\Form\TemplateModificationForm;
use Thelia\Form\TemplateCreationForm;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\TemplateAv;
use Thelia\Model\TemplateAvQuery;
use Thelia\Core\Event\TemplateAvUpdateEvent;
use Thelia\Core\Event\TemplateEvent;
use Thelia\Core\Event\TemplateDeleteAttributeEvent;
use Thelia\Core\Event\TemplateAddAttributeEvent;
use Thelia\Core\Event\TemplateAddFeatureEvent;
use Thelia\Core\Event\TemplateDeleteFeatureEvent;

/**
 * Manages product templates
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class TemplateController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'template',
            null,
            null,

            'admin.configuration.templates.view',
            'admin.configuration.templates.create',
            'admin.configuration.templates.update',
            'admin.configuration.templates.delete',

            TheliaEvents::TEMPLATE_CREATE,
            TheliaEvents::TEMPLATE_UPDATE,
            TheliaEvents::TEMPLATE_DELETE,
            null, // No visibility toggle
            null // No position update
        );
    }

    protected function getCreationForm()
    {
        return new TemplateCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new TemplateModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new TemplateCreateEvent();

        $createEvent
            ->setTemplateName($formData['name'])
            ->setLocale($formData["locale"])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new TemplateUpdateEvent($formData['id']);

        // Create and dispatch the change event
        $changeEvent
            ->setLocale($formData["locale"])
            ->setTemplateName($formData['name'])
        ;

        // Add feature and attributes list

        return $changeEvent;
    }

    protected function getDeleteEvent()
    {
        return new TemplateDeleteEvent($this->getRequest()->get('template_id'));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasTemplate();
    }

    protected function hydrateObjectForm($object)
    {

        $data = array(
            'id'      => $object->getId(),
            'locale'  => $object->getLocale(),
            'name'    => $object->getName()
        );

        // Setup the object form
        return new TemplateModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasTemplate() ? $event->getTemplate() : null;
    }

    protected function getExistingObject()
    {
        return TemplateQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('template_id'));
    }

    protected function getObjectLabel($object)
    {
        return $object->getName();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function renderListTemplate($currentOrder)
    {
        return $this->render('templates', array('order' => $currentOrder));
    }

    protected function renderEditionTemplate()
    {
        return $this->render(
                'template-edit',
                array(
                        'template_id' => $this->getRequest()->get('template_id'),
                )
        );
    }

    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute(
                "admin.configuration.templates.update",
                array(
                        'template_id' => $this->getRequest()->get('template_id'),
                )
        );
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute('admin.configuration.templates.default');
    }

    // Process delete failure, which may occurs if template is in use.
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        if ($deleteEvent->getProductCount() > 0) {

            $this->getParserContext()->setGeneralError(
                $this->getTranslator()->trans(
                        "This template is in use in some of your products, and cannot be deleted. Delete it from all your products and try again."
                )
            );

            return $this->renderList();
        }

        // Normal delete processing
        return null;
    }

    public function getAjaxFeaturesAction() {
        return $this->render(
                'ajax/template-feature-list',
                array('template_id' => $this->getRequest()->get('template_id'))
        );
    }

    public function getAjaxAttributesAction() {
        return $this->render(
                'ajax/template-attribute-list',
                array('template_id' => $this->getRequest()->get('template_id'))
        );
    }

    public function addAttributeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.template.attribute.add")) return $response;

        $attribute_id = intval($this->getRequest()->get('attribute_id'));

        if ($attribute_id > 0) {
            $event = new TemplateAddAttributeEvent(
                    $this->getExistingObject(),
                    $attribute_id
            );

            try {
                $this->dispatch(TheliaEvents::TEMPLATE_ADD_ATTRIBUTE, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteAttributeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.template.attribute.delete")) return $response;

        $event = new TemplateDeleteAttributeEvent(
                $this->getExistingObject(),
                intval($this->getRequest()->get('attribute_id'))
        );

        try {
            $this->dispatch(TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToEditionTemplate();
    }

    public function addFeatureAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.template.feature.add")) return $response;

        $feature_id = intval($this->getRequest()->get('feature_id'));

        if ($feature_id > 0) {
            $event = new TemplateAddFeatureEvent(
                    $this->getExistingObject(),
                    $feature_id
            );

            try {
                $this->dispatch(TheliaEvents::TEMPLATE_ADD_FEATURE, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteFeatureAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.template.feature.delete")) return $response;

        $event = new TemplateDeleteFeatureEvent(
                $this->getExistingObject(),
                intval($this->getRequest()->get('feature_id'))
        );

        try {
            $this->dispatch(TheliaEvents::TEMPLATE_DELETE_FEATURE, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToEditionTemplate();
    }

}
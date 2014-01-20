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

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Model\FeatureAvQuery;
use Thelia\Form\FeatureAvModificationForm;
use Thelia\Form\FeatureAvCreationForm;
use Thelia\Core\Event\UpdatePositionEvent;

/**
 * Manages features-av
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class FeatureAvController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'featureav',
            'manual',
            'order',

            AdminResources::FEATURE,

            TheliaEvents::FEATURE_AV_CREATE,
            TheliaEvents::FEATURE_AV_UPDATE,
            TheliaEvents::FEATURE_AV_DELETE,
            null, // No visibility toggle
            TheliaEvents::FEATURE_AV_UPDATE_POSITION
        );
    }

    protected function getCreationForm()
    {
        return new FeatureAvCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new FeatureAvModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new FeatureAvCreateEvent();

        $createEvent
            ->setFeatureId($formData['feature_id'])
            ->setTitle($formData['title'])
            ->setLocale($formData["locale"])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new FeatureAvUpdateEvent($formData['id']);

        // Create and dispatch the change event
        $changeEvent
            ->setLocale($formData["locale"])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
        ;

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
                $this->getRequest()->get('featureav_id', null),
                $positionChangeMode,
                $positionValue
        );
    }

    protected function getDeleteEvent()
    {
        return new FeatureAvDeleteEvent($this->getRequest()->get('featureav_id'));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasFeatureAv();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'chapo'        => $object->getChapo(),
            'description'  => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum()
        );

        // Setup the object form
        return new FeatureAvModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasFeatureAv() ? $event->getFeatureAv() : null;
    }

    protected function getExistingObject()
    {
        $featureAv = FeatureAvQuery::create()
        ->findOneById($this->getRequest()->get('featureav_id', 0));

        if (null !== $featureAv) {
            $featureAv->setLocale($this->getCurrentEditionLocale());
        }

        return $featureAv;
    }

    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return array(
            'feature_id' => $this->getRequest()->get('feature_id'),
            'order' => $this->getCurrentListOrder()
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
                'feature-edit',
                $this->getViewArguments()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('feature-edit', $this->getViewArguments());
    }

    protected function redirectToEditionTemplate()
    {
        // We always return to the feature edition form
        $this->redirectToRoute(
                "admin.configuration.features.update",
                $this->getViewArguments()
        );
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
                "admin.configuration.features.update",
                $this->getViewArguments()
        );
     }
}

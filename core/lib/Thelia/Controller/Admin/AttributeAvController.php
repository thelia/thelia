<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;

/**
 * Manages attributes-av
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AttributeAvController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'attributeav',
            'manual',
            'order',
            AdminResources::ATTRIBUTE,
            TheliaEvents::ATTRIBUTE_AV_CREATE,
            TheliaEvents::ATTRIBUTE_AV_UPDATE,
            TheliaEvents::ATTRIBUTE_AV_DELETE,
            null, // No visibility toggle
            TheliaEvents::ATTRIBUTE_AV_UPDATE_POSITION
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::ATTRIBUTE_AV_CREATION);
    }

    protected function getUpdateForm()
    {
        throw new \LogicException("Attribute Av. modification is not yet implemented");
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new AttributeAvCreateEvent();

        $createEvent
            ->setAttributeId($formData['attribute_id'])
            ->setTitle($formData['title'])
            ->setLocale($formData["locale"])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new AttributeAvUpdateEvent($formData['id']);

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
            $this->getRequest()->get('attributeav_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function getDeleteEvent()
    {
        return new AttributeAvDeleteEvent($this->getRequest()->get('attributeav_id'));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasAttributeAv();
    }

    protected function hydrateObjectForm($object)
    {
        throw new \LogicException("Attribute Av. modification is not yet implemented");
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasAttributeAv() ? $event->getAttributeAv() : null;
    }

    protected function getExistingObject()
    {
        $attributeAv =  AttributeAvQuery::create()
        ->findOneById($this->getRequest()->get('attributeav_id', 0));

        if (null !== $attributeAv) {
            $attributeAv->setLocale($this->getCurrentEditionLocale());
        }

        return $attributeAv;
    }

    /**
     * @param AttributeAv $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param AttributeAv $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return array(
            'attribute_id' => $this->getRequest()->get('attribute_id'),
            'order' => $this->getCurrentListOrder()
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the attribute edition form
        return $this->render(
            'attribute-edit',
            $this->getViewArguments()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the attribute edition form
        return $this->render('attribute-edit', $this->getViewArguments());
    }

    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.attributes.update",
            $this->getViewArguments()
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.attributes.update",
            $this->getViewArguments()
        );
    }
}

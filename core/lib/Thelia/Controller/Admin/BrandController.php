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

use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Brand\BrandModificationForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;

/**
 * Class BrandController
 * @package Thelia\Controller\Admin
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'brand',
            'manual',
            'order',
            AdminResources::BRAND,
            TheliaEvents::BRAND_CREATE,
            TheliaEvents::BRAND_UPDATE,
            TheliaEvents::BRAND_DELETE,
            TheliaEvents::BRAND_TOGGLE_VISIBILITY,
            TheliaEvents::BRAND_UPDATE_POSITION,
            TheliaEvents::BRAND_UPDATE_SEO
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::BRAND_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::BRAND_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param  Brand                 $object
     * @return BrandModificationForm $object
     */
    protected function hydrateObjectForm($object)
    {
        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($object);

        // Prepare the data that will hydrate the form
        $data = [
            'id'            => $object->getId(),
            'locale'        => $object->getLocale(),
            'title'         => $object->getTitle(),
            'chapo'         => $object->getChapo(),
            'description'   => $object->getDescription(),
            'postscriptum'  => $object->getPostscriptum(),
            'visible'       => $object->getVisible() ? true : false,
            'logo_image_id' => $object->getLogoImageId()
        ];

        // Setup the object form
        return $this->createForm(AdminForm::BRAND_MODIFICATION, "form", $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param  array            $formData
     * @return BrandCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $brandCreateEvent = new BrandCreateEvent();

        $brandCreateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setVisible($formData['visible'])
        ;

        return $brandCreateEvent;
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param  array            $formData
     * @return BrandUpdateEvent
     */
    protected function getUpdateEvent($formData)
    {
        $brandUpdateEvent = new BrandUpdateEvent($formData['id']);

        $brandUpdateEvent
            ->setLogoImageId($formData['logo_image_id'])
            ->setVisible($formData['visible'])
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
         ;

        return $brandUpdateEvent;
    }

    /**
     * Creates the delete event with the provided form data
     *
     * @return BrandDeleteEvent
     */
    protected function getDeleteEvent()
    {
        return new BrandDeleteEvent($this->getRequest()->get('brand_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param  BrandEvent $event
     * @return bool
     */
    protected function eventContainsObject($event)
    {
        return $event->hasBrand();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\Brand\BrandEvent
     *
     * @return null|\Thelia\Model\Brand
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getBrand();
    }

    /**
     * Load an existing object from the database
     *
     * @return \Thelia\Model\Brand
     */
    protected function getExistingObject()
    {
        $brand = BrandQuery::create()
            ->findOneById($this->getRequest()->get('brand_id', 0));

        if (null !== $brand) {
            $brand->setLocale($this->getCurrentEditionLocale());
        }

        return $brand;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param Brand $object
     *
     * @return string brand title
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param Brand $object
     *
     * @return int brand id
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param string $currentOrder, if any, null otherwise.
     *
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('brand', 'order', 'manual');

        return $this->render('brands', [
            'order' => $currentOrder,
        ]);
    }

    protected function getEditionArguments()
    {
        return [
            'brand_id' => $this->getRequest()->get('brand_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general')
        ];
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('brand-edit', $this->getEditionArguments());
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.brand.update',
            [],
            $this->getEditionArguments()
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.brand.default');
    }

    /**
     * @return BrandToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent()
    {
        return new BrandToggleVisibilityEvent($this->getExistingObject());
    }

    /**
     * @inheritdoc
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('brand_id', null),
            $positionChangeMode,
            $positionValue
        );
    }
}

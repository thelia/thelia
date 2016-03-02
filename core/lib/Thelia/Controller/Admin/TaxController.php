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

use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Tax;
use Thelia\Model\TaxQuery;

class TaxController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'tax',
            'manual',
            'order',
            AdminResources::TAX,
            TheliaEvents::TAX_CREATE,
            TheliaEvents::TAX_UPDATE,
            TheliaEvents::TAX_DELETE
        );
    }

    protected function getCreationForm()
    {
        $form = $this->createForm(AdminForm::TAX_CREATION);

        return $form;
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::TAX_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        $event = new TaxEvent();

        $event->setLocale($formData['locale']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);
        $event->setType(Tax::unescapeTypeName($formData['type']));
        $event->setRequirements($this->getRequirements($formData['type'], $formData));

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new TaxEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);
        $event->setType(Tax::unescapeTypeName($formData['type']));
        $event->setRequirements($this->getRequirements($formData['type'], $formData));

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new TaxEvent();

        $event->setId(
            $this->getRequest()->get('tax_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasTax();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'description'  => $object->getDescription(),
            'type'         => Tax::escapeTypeName($object->getType()),
        );

        // Setup the object form
        return $this->createForm(
            AdminForm::TAX_MODIFICATION,
            "form",
            $data
        );
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasTax() ? $event->getTax() : null;
    }

    protected function getExistingObject()
    {
        $tax = TaxQuery::create()
            ->findOneById($this->getRequest()->get('tax_id', 0));

        if (null !== $tax) {
            $tax->setLocale($this->getCurrentEditionLocale());
        }

        return $tax;
    }

    /**
     * @param Tax $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param Tax $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return array();
    }

    protected function getRouteArguments($tax_id = null)
    {
        return array(
            'tax_id' => $tax_id === null ? $this->getRequest()->get('tax_id') : $tax_id,
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        return $this->render(
            'taxes-rules',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('tax-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.taxes.update",
            $this->getViewArguments($country),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  TaxEvent $createEvent the create event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.taxes.update",
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getTax()->getId())
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute("admin.configuration.taxes-rules.list");
    }

    protected function getRequirements($type, $formData)
    {
        $requirements = array();
        foreach ($formData as $data => $value) {
            if (!strstr($data, ':')) {
                continue;
            }

            $couple = explode(':', $data);

            if (count($couple) == 2 && $couple[0] == $type) {
                $requirements[$couple[1]] = $value;
            }
        }

        return $requirements;
    }
}

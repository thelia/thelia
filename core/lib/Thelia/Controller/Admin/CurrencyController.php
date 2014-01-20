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
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Model\CurrencyQuery;
use Thelia\Form\CurrencyModificationForm;
use Thelia\Form\CurrencyCreationForm;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;

/**
 * Manages currencies
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class CurrencyController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'currency',
            'manual',
            'order',

            AdminResources::CURRENCY,

            TheliaEvents::CURRENCY_CREATE,
            TheliaEvents::CURRENCY_UPDATE,
            TheliaEvents::CURRENCY_DELETE,
            null, // No visibility toggle
            TheliaEvents::CURRENCY_UPDATE_POSITION
        );
    }

    protected function getCreationForm()
    {
        return new CurrencyCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new CurrencyModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new CurrencyCreateEvent();

        $createEvent
        ->setCurrencyName($formData['name'])
        ->setLocale($formData["locale"])
        ->setSymbol($formData['symbol'])
        ->setCode($formData['code'])
        ->setRate($formData['rate'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new CurrencyUpdateEvent($formData['id']);

        // Create and dispatch the change event
        $changeEvent
        ->setCurrencyName($formData['name'])
        ->setLocale($formData["locale"])
        ->setSymbol($formData['symbol'])
        ->setCode($formData['code'])
        ->setRate($formData['rate'])
        ;

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
                $this->getRequest()->get('currency_id', null),
                $positionChangeMode,
                $positionValue
        );
    }

    protected function getDeleteEvent()
    {
        return new CurrencyDeleteEvent($this->getRequest()->get('currency_id'));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasCurrency();
    }

    protected function hydrateObjectForm($object)
    {
        // Prepare the data that will hydrate the form
        $data = array(
                'id'     => $object->getId(),
                'name'   => $object->getName(),
                'locale' => $object->getLocale(),
                'code'   => $object->getCode(),
                'symbol' => $object->getSymbol(),
                'rate'   => $object->getRate()
        );

        // Setup the object form
        return new CurrencyModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasCurrency() ? $event->getCurrency() : null;
    }

    protected function getExistingObject()
    {
        $currency =  CurrencyQuery::create()
        ->findOneById($this->getRequest()->get('currency_id'));

        if (null !== $currency) {
            $currency->setLocale($this->getCurrentEditionLocale());
        }

        return $currency;
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
        return $this->render('currencies', array('order' => $currentOrder));
    }

    protected function renderEditionTemplate()
    {
        return $this->render('currency-edit', array('currency_id' => $this->getRequest()->get('currency_id')));
    }

    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute(
                "admin.configuration.currencies.update",
                array('currency_id' => $this->getRequest()->get('currency_id'))
        );
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute('admin.configuration.currencies.default');
    }

    /**
     * Update currencies rates
     */
    public function updateRatesAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        try {
            $this->dispatch(TheliaEvents::CURRENCY_UPDATE_RATES);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToListTemplate();
    }

    /**
     * Sets the default currency
     */
    public function setDefaultAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $changeEvent = new CurrencyUpdateEvent($this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true);

        try {
            $this->dispatch(TheliaEvents::CURRENCY_SET_DEFAULT, $changeEvent);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToListTemplate();
    }

}

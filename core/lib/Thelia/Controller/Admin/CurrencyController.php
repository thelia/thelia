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

use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\CurrencyQuery;

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
        return $this->createForm(AdminForm::CURRENCY_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::CURRENCY_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new CurrencyCreateEvent();

        $createEvent
        ->setCurrencyName($formData['name'])
        ->setLocale($formData["locale"])
        ->setSymbol($formData['symbol'])
        ->setFormat($formData['format'])
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
        ->setFormat($formData['format'])
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
                'format' => $object->getFormat(),
                'rate'   => $object->getRate()
        );

        // Setup the object form
        return $this->createForm(AdminForm::CURRENCY_MODIFICATION, "form", $data);
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
        return $this->generateRedirectFromRoute(
            "admin.configuration.currencies.update",
            [
                'currency_id' => $this->getRequest()->get('currency_id'),
            ]
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.configuration.currencies.default');
    }

    /**
     * Update currencies rates
     */
    public function updateRatesAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        try {
            $event = new CurrencyUpdateRateEvent();

            $this->dispatch(TheliaEvents::CURRENCY_UPDATE_RATES, $event);

            if ($event->hasUndefinedRates()) {
                return $this->render('currencies', [
                    'undefined_rates' => $event->getUndefinedRates()
                ]);
            }

        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->redirectToListTemplate();
    }

    /**
     * Sets the default currency
     */
    public function setDefaultAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $changeEvent = new CurrencyUpdateEvent((int) $this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true)->setVisible(1);

        try {
            $this->dispatch(TheliaEvents::CURRENCY_SET_DEFAULT, $changeEvent);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->redirectToListTemplate();
    }

    /**
     * Sets if the currency is visible for Front
     */
    public function setVisibleAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $changeEvent = new CurrencyUpdateEvent((int) $this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setVisible((int) $this->getRequest()->get('visible', 0));

        try {
            $this->dispatch(TheliaEvents::CURRENCY_SET_VISIBLE, $changeEvent);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->redirectToListTemplate();
    }
}

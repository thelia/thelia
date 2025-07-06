<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Manages currencies.
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
            TheliaEvents::CURRENCY_UPDATE_POSITION,
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::CURRENCY_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::CURRENCY_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new CurrencyCreateEvent();

        $createEvent
            ->setCurrencyName($formData['name'])
            ->setLocale($formData['locale'])
            ->setSymbol($formData['symbol'])
            ->setFormat($formData['format'])
            ->setCode($formData['code'])
            ->setRate($formData['rate']);

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $changeEvent = new CurrencyUpdateEvent($formData['id']);

        $changeEvent
            ->setCurrencyName($formData['name'])
            ->setLocale($formData['locale'])
            ->setSymbol($formData['symbol'])
            ->setFormat($formData['format'])
            ->setCode($formData['code'])
            ->setRate($formData['rate']);

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('currency_id'),
            $positionChangeMode,
            $positionValue,
        );
    }

    protected function getDeleteEvent(): CurrencyDeleteEvent
    {
        return new CurrencyDeleteEvent($this->getRequest()->get('currency_id'));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasCurrency();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        // Prepare the data that will hydrate the form
        $data = [
            'id' => $object->getId(),
            'name' => $object->getName(),
            'locale' => $object->getLocale(),
            'code' => $object->getCode(),
            'symbol' => $object->getSymbol(),
            'format' => $object->getFormat(),
            'rate' => $object->getRate(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::CURRENCY_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasCurrency() ? $event->getCurrency() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $currency = CurrencyQuery::create()
            ->findOneById($this->getRequest()->get('currency_id'));

        if (null !== $currency) {
            $currency->setLocale($this->getCurrentEditionLocale());
        }

        return $currency;
    }

    /**
     * @param Currency $object
     */
    protected function getObjectLabel(ActiveRecordInterface $object): ?string
    {
        return $object->getName();
    }

    /**
     * @param Currency $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        return $this->render('currencies', ['order' => $currentOrder]);
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render('currency-edit', ['currency_id' => $this->getRequest()->get('currency_id')]);
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.currencies.update',
            [
                'currency_id' => $this->getRequest()->get('currency_id'),
            ],
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.currencies.default');
    }

    /**
     * Update currencies rates.
     */
    public function updateRatesAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        try {
            $event = new CurrencyUpdateRateEvent();

            $eventDispatcher->dispatch($event, TheliaEvents::CURRENCY_UPDATE_RATES);

            if ($event->hasUndefinedRates()) {
                return $this->render('currencies', [
                    'undefined_rates' => $event->getUndefinedRates(),
                ]);
            }
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        return $this->redirectToListTemplate();
    }

    /**
     * Sets the default currency.
     */
    public function setDefaultAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $changeEvent = new CurrencyUpdateEvent((int) $this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true)->setVisible(1);

        try {
            $eventDispatcher->dispatch($changeEvent, TheliaEvents::CURRENCY_SET_DEFAULT);
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        return $this->redirectToListTemplate();
    }

    /**
     * Sets if the currency is visible for Front.
     */
    public function setVisibleAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $changeEvent = new CurrencyUpdateEvent((int) $this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setVisible((int) $this->getRequest()->get('visible', 0));

        try {
            $eventDispatcher->dispatch($changeEvent, TheliaEvents::CURRENCY_SET_VISIBLE);
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        return $this->redirectToListTemplate();
    }
}

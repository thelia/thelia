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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Form\Currency\CurrencyType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/currencies', name: 'admin.configuration.currencies.')]
final class CurrencyController
{
    private const RESOURCE = AdminResources::CURRENCY;
    private const LIST_ROUTE = 'admin.configuration.currencies.default';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/currency/list.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly TokenProvider $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $undefinedRates = $request->getSession()->getFlashBag()->get('currency_undefined_rates');

        return new Response($this->twig->render(
            self::LIST_TEMPLATE,
            $this->buildListContext(undefinedRates: $undefinedRates),
        ));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_currency_create', CurrencyType::class, null, [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::CURRENCY_CREATE,
            eventFactory: function (FormInterface $validated): CurrencyCreateEvent {
                $event = new CurrencyCreateEvent();
                $this->hydrateCurrencyCreate($event, $validated);

                return $event;
            },
            actionLabel: 'Currency creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (CurrencyCreateEvent $event): array => $this->describeCreated($event),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateRedirect(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(): Response
    {
        $form = $this->formFactory->createNamed('thelia_currency_update', CurrencyType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::CURRENCY_UPDATE,
            eventFactory: function (FormInterface $validated): CurrencyUpdateEvent {
                $event = new CurrencyUpdateEvent((int) $validated->get('id')->getData());
                $this->hydrateCurrencyUpdate($event, $validated);

                return $event;
            },
            actionLabel: 'Currency update',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (CurrencyUpdateEvent $event): array => $this->describeUpdated($event),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new CurrencyDeleteEvent((int) $request->get('currency_id', 0)),
            eventName: TheliaEvents::CURRENCY_DELETE,
            actionLabel: 'Currency deletion',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
        );
    }

    #[Route('/set-default', name: 'set-default', methods: ['GET'])]
    public function setDefault(Request $request): Response
    {
        $event = new CurrencyUpdateEvent((int) $request->query->get('currency_id', '0'));
        $event->setIsDefault(1)->setVisible(1);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CURRENCY_SET_DEFAULT,
            actionLabel: 'Currency set default',
            successRoute: self::LIST_ROUTE,
            describeForLog: fn (CurrencyUpdateEvent $event): array => $this->describeUpdated($event, 'set as default'),
        );
    }

    #[Route('/set-visible', name: 'set-visible', methods: ['GET'])]
    public function setVisible(Request $request): Response
    {
        $event = new CurrencyUpdateEvent((int) $request->query->get('currency_id', '0'));
        $event->setVisible((int) $request->query->get('visible', '0'));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CURRENCY_SET_VISIBLE,
            actionLabel: 'Currency set visibility',
            successRoute: self::LIST_ROUTE,
            describeForLog: fn (CurrencyUpdateEvent $event): array => $this->describeUpdated($event, 'visibility changed'),
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('currency_id', 0),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CURRENCY_UPDATE_POSITION,
            actionLabel: 'Currency reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-rates', name: 'update-rates', methods: ['POST', 'GET'])]
    public function updateRates(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $event = new CurrencyUpdateRateEvent();

        try {
            $this->events->dispatch($event, TheliaEvents::CURRENCY_UPDATE_RATES);

            if ($event->hasUndefinedRates()) {
                $request->getSession()->getFlashBag()->set('currency_undefined_rates', $event->getUndefinedRates());
            }

            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        } catch (\Throwable $exception) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $this->translator->trans('Currency rates update failed: %error', ['%error' => $exception->getMessage()]),
            );

            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }
    }

    private function hydrateCurrencyCreate(CurrencyCreateEvent $event, FormInterface $form): void
    {
        $event
            ->setCurrencyName((string) $form->get('name')->getData())
            ->setLocale((string) $form->get('locale')->getData())
            ->setCode((string) $form->get('code')->getData())
            ->setSymbol((string) $form->get('symbol')->getData())
            ->setFormat((string) $form->get('format')->getData())
            ->setRate((float) $form->get('rate')->getData());
    }

    private function hydrateCurrencyUpdate(CurrencyUpdateEvent $event, FormInterface $form): void
    {
        $event
            ->setCurrencyName((string) $form->get('name')->getData())
            ->setLocale((string) $form->get('locale')->getData())
            ->setCode((string) $form->get('code')->getData())
            ->setSymbol((string) $form->get('symbol')->getData())
            ->setFormat((string) $form->get('format')->getData())
            ->setRate((float) $form->get('rate')->getData());
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(CurrencyCreateEvent $event): array
    {
        if (!$event->hasCurrency()) {
            throw new \LogicException($this->translator->trans('No currency was created.'));
        }

        $currency = $event->getCurrency();

        return [\sprintf('Currency %s (ID %d) created', $currency->getName(), $currency->getId()), $currency->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(CurrencyUpdateEvent $event, string $context = 'modified'): array
    {
        if (!$event->hasCurrency()) {
            throw new \LogicException($this->translator->trans('No currency was updated.'));
        }

        $currency = $event->getCurrency();

        return [\sprintf('Currency %s (ID %d) %s', $currency->getName(), $currency->getId(), $context), $currency->getId()];
    }

    private function renderListWithError(): Response
    {
        return new Response(
            $this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()),
            Response::HTTP_BAD_REQUEST,
        );
    }

    /**
     * @param list<int> $undefinedRates
     *
     * @return array<string, mixed>
     */
    private function buildListContext(array $undefinedRates = []): array
    {
        $currencies = CurrencyQuery::create()->orderByPosition()->find();
        $rows = [];
        $editForms = [];

        foreach ($currencies as $currency) {
            $rows[] = $this->currencyToRow($currency);
            $editForms[$currency->getId()] = $this->createEditForm($currency)->createView();
        }

        $createForm = $this->formFactory->createNamed('thelia_currency_create', CurrencyType::class, null, [
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'edit_forms' => $editForms,
            'create_form' => $createForm->createView(),
            'undefined_rates' => $this->resolveUndefinedRateNames($undefinedRates),
            'update_position_url' => $this->urls->generate('admin.configuration.currencies.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currencyToRow(Currency $currency): array
    {
        $id = $currency->getId();
        $isDefault = (bool) $currency->getByDefault();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this currency'),
                modalTarget: '#currency-edit-modal-'.$id,
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['currency-id' => $id],
            ),
        ];

        if (!$isDefault) {
            $actions[] = new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this currency'),
                modalTarget: '#currency-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['currency-id' => $id, 'currency-name' => $currency->getName()],
            );
        }

        return [
            'id' => $id,
            'name' => $currency->getName(),
            'code' => $currency->getCode(),
            'symbol' => $currency->getSymbol(),
            'rate' => $currency->getRate(),
            'position' => $currency->getPosition(),
            'visible' => (bool) $currency->getVisible(),
            'default' => $isDefault,
            'toggle_visible_url' => $this->tokenizedUrl('admin.configuration.currencies.set-visible', [
                'currency_id' => $id,
                'visible' => $currency->getVisible() ? 0 : 1,
            ]),
            'toggle_default_url' => $this->tokenizedUrl('admin.configuration.currencies.set-default', ['currency_id' => $id]),
            '_actions' => $actions,
        ];
    }

    private function createEditForm(Currency $currency): FormInterface
    {
        return $this->formFactory->createNamed('thelia_currency_update_'.$currency->getId(), CurrencyType::class, [
            'id' => $currency->getId(),
            'name' => $currency->getName(),
            'locale' => $currency->getLocale(),
            'code' => $currency->getCode(),
            'symbol' => $currency->getSymbol(),
            'format' => $currency->getFormat(),
            'rate' => $currency->getRate(),
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<string>
     */
    private function resolveUndefinedRateNames(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $names = [];
        foreach (CurrencyQuery::create()->filterById($ids)->find() as $currency) {
            $names[] = \sprintf('%s (%s)', $currency->getName(), $currency->getCode());
        }

        return $names;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function tokenizedUrl(string $route, array $parameters): string
    {
        $url = $this->urls->generate($route, $parameters);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'_token='.$this->tokens->assignToken();
    }
}

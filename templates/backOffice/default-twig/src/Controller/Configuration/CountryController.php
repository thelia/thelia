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

use BackOfficeDefaultTwigBundle\Form\Configuration\CountryType;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryToggleVisibilityEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\StateQuery;
use Twig\Environment;

#[Route('/admin/configuration/countries', name: 'admin.configuration.countries.')]
final class CountryController
{
    private const RESOURCE = AdminResources::COUNTRY;
    private const LIST_ROUTE = 'admin.configuration.countries.default';
    private const EDIT_ROUTE = 'admin.configuration.countries.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/country/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/country/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $countries = CountryQuery::create()->orderByTitle()->find();
        $rows = [];
        foreach ($countries as $country) {
            \assert($country instanceof Country);
            $country->setLocale($locale);
            $rows[] = $this->countryToRow($country);
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
        ]));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_country_create', CountryType::class, [
            'locale' => $this->defaultLocale(),
        ], ['csrf_protection' => false]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::COUNTRY_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Country creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{country_id}', name: 'update', methods: ['GET'], requirements: ['country_id' => '\d+'])]
    public function updateView(int $country_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $country = CountryQuery::create()->findPk($country_id);
        if ($country === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $country->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'country' => $country,
            'form' => $this->buildUpdateForm($country, $locale)->createView(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_country_update', CountryType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $countryId = (int) $request->request->get('country_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::COUNTRY_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Country update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['country_id' => $countryId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['country_id' => $countryId])),
        );
    }

    #[Route('/toggle-visibility', name: 'toggle-visibility', methods: ['GET', 'POST'])]
    public function toggleVisibility(Request $request): Response
    {
        $country = CountryQuery::create()->findPk((int) $request->get('country_id', 0));
        if ($country === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new CountryToggleVisibilityEvent($country),
            eventName: TheliaEvents::COUNTRY_TOGGLE_VISIBILITY,
            actionLabel: 'Country visibility',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/toggle-default', name: 'toggle-default', methods: ['GET', 'POST'])]
    public function toggleDefault(Request $request): Response
    {
        $country = CountryQuery::create()->findPk((int) $request->get('country_id', 0));
        if ($country === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new CountryToggleDefaultEvent($country),
            eventName: TheliaEvents::COUNTRY_TOGGLE_DEFAULT,
            actionLabel: 'Country default',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new CountryDeleteEvent((int) $request->get('country_id', 0)),
            eventName: TheliaEvents::COUNTRY_DELETE,
            actionLabel: 'Country deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $visible = $request->query->getBoolean('visible', true);
        $locale = (string) ($request->query->get('locale') ?? $this->defaultLocale());

        $countriesQuery = CountryQuery::create();
        if ($visible) {
            $countriesQuery->filterByVisible(true);
        }
        $countriesQuery->joinWithI18n($locale);

        $payload = [];
        foreach ($countriesQuery->find() as $country) {
            \assert($country instanceof Country);
            $entry = [
                'id' => (int) $country->getId(),
                'title' => (string) $country->getTitle(),
                'hasStates' => (bool) $country->getHasStates(),
                'states' => [],
            ];

            if ($entry['hasStates']) {
                $statesQuery = StateQuery::create()->filterByCountryId($country->getId());
                if ($visible) {
                    $statesQuery->filterByVisible(true);
                }
                $statesQuery->joinWithI18n($locale);

                foreach ($statesQuery->find() as $state) {
                    $entry['states'][] = [
                        'id' => (int) $state->getId(),
                        'title' => (string) $state->getTitle(),
                    ];
                }
            }

            $payload[] = $entry;
        }

        return new JsonResponse($payload);
    }

    private function createEvent(FormInterface $validated): CountryCreateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new CountryCreateEvent();
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setIsocode((string) ($data['isocode'] ?? ''))
            ->setIsoAlpha2((string) ($data['isoalpha2'] ?? ''))
            ->setIsoAlpha3((string) ($data['isoalpha3'] ?? ''))
            ->setArea((int) ($data['area'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setHasStates((bool) ($data['has_states'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): CountryUpdateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new CountryUpdateEvent((int) ($data['id'] ?? 0));
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setIsocode((string) ($data['isocode'] ?? ''))
            ->setIsoAlpha2((string) ($data['isoalpha2'] ?? ''))
            ->setIsoAlpha3((string) ($data['isoalpha3'] ?? ''))
            ->setArea((int) ($data['area'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setHasStates((bool) ($data['has_states'] ?? false));

        return $event;
    }

    private function buildUpdateForm(Country $country, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_country_update', CountryType::class, [
            'id' => $country->getId(),
            'locale' => $locale,
            'title' => $country->getTitle(),
            'isocode' => $country->getIsocode(),
            'isoalpha2' => $country->getIsoalpha2(),
            'isoalpha3' => $country->getIsoalpha3(),
            'area' => null,
            'visible' => (bool) $country->getVisible(),
            'has_states' => (bool) $country->getHasStates(),
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function countryToRow(Country $country): array
    {
        $id = (int) $country->getId();
        $actions = [
            new RowAction(kind: 'edit', label: $this->translator->trans('Edit'), href: $this->urls->generate(self::EDIT_ROUTE, ['country_id' => $id]), grantedAttribute: AccessManager::UPDATE, grantedSubject: self::RESOURCE),
            new RowAction(kind: 'delete', label: $this->translator->trans('Delete'), modalTarget: '#country-delete-modal', grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE, dataAttributes: ['country-id' => $id, 'country-label' => (string) $country->getTitle()]),
        ];

        return [
            'id' => $id,
            'title' => (string) $country->getTitle(),
            'isoalpha2' => (string) $country->getIsoalpha2(),
            'isoalpha3' => (string) $country->getIsoalpha3(),
            'isocode' => (string) $country->getIsocode(),
            'visible' => (bool) $country->getVisible(),
            'default' => (bool) $country->getByDefault(),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}

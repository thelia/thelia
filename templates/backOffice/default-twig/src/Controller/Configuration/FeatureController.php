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

use BackOfficeDefaultTwigBundle\Form\Feature\FeatureAvType;
use BackOfficeDefaultTwigBundle\Form\Feature\FeatureType;
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
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\Feature\FeatureCreateEvent;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\Feature\FeatureEvent;
use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/features', name: 'admin.configuration.features.')]
final class FeatureController
{
    private const RESOURCE = AdminResources::FEATURE;
    private const LIST_ROUTE = 'admin.configuration.features.default';
    private const EDIT_ROUTE = 'admin.configuration.features.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/feature/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/feature/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly EventDispatcherInterface $events,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_feature_creation', FeatureType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'include_creation_extras' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::FEATURE_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Feature creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
            describeForLog: $this->describeCreated(...),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateView(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $featureId = (int) $request->query->get('feature_id', 0);
        $feature = FeatureQuery::create()->findPk($featureId);
        if ($feature === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $feature->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'feature' => $feature,
            'form' => $this->buildUpdateForm($feature, $locale)->createView(),
            'av_create_form' => $this->buildAvCreateForm($featureId, $locale)->createView(),
            'feature_avs' => $this->avRows($feature, $locale),
            'av_position_url' => $this->urls->generate('admin.configuration.features-av.update-position'),
            'av_position_token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_feature_modification', FeatureType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        $featureId = (int) $request->request->get('feature_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::FEATURE_UPDATE,
            eventFactory: function (FormInterface $validated) use ($request): FeatureUpdateEvent {
                $event = $this->updateEvent($validated);
                $this->processFeatureValues($request, $event->getLocale() ?? $this->defaultLocale());

                return $event;
            },
            actionLabel: 'Feature update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['feature_id' => $featureId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['feature_id' => $featureId])),
            describeForLog: $this->describeUpdated(...),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new FeatureDeleteEvent((int) $request->get('feature_id', 0)),
            eventName: TheliaEvents::FEATURE_DELETE,
            actionLabel: 'Feature deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('feature_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::FEATURE_UPDATE_POSITION,
            actionLabel: 'Feature reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/add-to-all-templates', name: 'add-to-all', methods: ['POST', 'GET'])]
    public function addToAllTemplates(Request $request): Response
    {
        return $this->dispatchTemplateBulk($request, TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES, 'Feature added to all templates');
    }

    #[Route('/remove-from-all-templates', name: 'rem-from-all', methods: ['POST', 'GET'])]
    public function removeFromAllTemplates(Request $request): Response
    {
        return $this->dispatchTemplateBulk($request, TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES, 'Feature removed from all templates');
    }

    private function dispatchTemplateBulk(Request $request, string $eventName, string $label): Response
    {
        $feature = FeatureQuery::create()->findPk((int) $request->get('feature_id', 0));
        if ($feature === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new FeatureEvent($feature),
            eventName: $eventName,
            actionLabel: $label,
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): FeatureCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new FeatureCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setAddToAllTemplates((bool) ($data['add_to_all'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): FeatureUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new FeatureUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null));

        return $event;
    }

    private function processFeatureValues(Request $request, string $locale): void
    {
        $values = $request->request->all('feature_values');
        if ($values === []) {
            return;
        }

        foreach ($values as $id => $title) {
            if (!is_string($title)) {
                continue;
            }
            $event = new FeatureAvUpdateEvent((int) $id);
            $event->setTitle($title)->setLocale($locale);
            $this->events->dispatch($event, TheliaEvents::FEATURE_AV_UPDATE);
        }
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(FeatureCreateEvent $event): array
    {
        if (!$event->hasFeature()) {
            throw new \LogicException($this->translator->trans('No feature was created.'));
        }
        $feature = $event->getFeature();

        return [\sprintf('Feature %s (ID %d) created', (string) $feature->getTitle(), (int) $feature->getId()), (int) $feature->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(FeatureUpdateEvent $event): array
    {
        if (!$event->hasFeature()) {
            return ['Feature modified', null];
        }
        $feature = $event->getFeature();

        return [\sprintf('Feature %s (ID %d) modified', (string) $feature->getTitle(), (int) $feature->getId()), (int) $feature->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $features = FeatureQuery::create()->orderByPosition()->find();
        $rows = [];

        foreach ($features as $feature) {
            \assert($feature instanceof Feature);
            $feature->setLocale($locale);
            $rows[] = $this->featureToRow($feature);
        }

        $createForm = $this->formFactory->createNamed('thelia_feature_creation', FeatureType::class, [
            'locale' => $locale,
        ], [
            'include_creation_extras' => true,
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
            'update_position_url' => $this->urls->generate('admin.configuration.features.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function featureToRow(Feature $feature): array
    {
        $id = (int) $feature->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this feature'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['feature_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this feature'),
                modalTarget: '#feature-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: [
                    'feature-id' => $id,
                    'feature-label' => (string) $feature->getTitle(),
                ],
            ),
        ];

        return [
            'id' => $id,
            'title' => (string) $feature->getTitle(),
            'position' => (int) $feature->getPosition(),
            '_actions' => $actions,
        ];
    }

    private function buildUpdateForm(Feature $feature, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_feature_modification', FeatureType::class, [
            'id' => $feature->getId(),
            'locale' => $locale,
            'title' => $feature->getTitle(),
            'chapo' => $feature->getChapo(),
            'description' => $feature->getDescription(),
            'postscriptum' => $feature->getPostscriptum(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    private function buildAvCreateForm(int $featureId, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_featureav_creation', FeatureAvType::class, [
            'feature_id' => $featureId,
            'locale' => $locale,
        ], [
            'csrf_protection' => false,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function avRows(Feature $feature, string $locale): array
    {
        $rows = [];
        $avs = FeatureAvQuery::create()->filterByFeatureId($feature->getId())->orderByPosition()->find();
        foreach ($avs as $av) {
            $av->setLocale($locale);
            $rows[] = [
                'id' => (int) $av->getId(),
                'title' => (string) $av->getTitle(),
                'position' => (int) $av->getPosition(),
                'delete_url' => $this->tokenizedUrl('admin.configuration.features-av.delete', ['featureav_id' => (int) $av->getId()]),
            ];
        }

        return $rows;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_scalar($value) && $value !== null) {
            return null;
        }
        $cast = (string) $value;

        return $cast === '' ? null : $cast;
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

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

use BackOfficeDefaultTwigBundle\Form\Attribute\AttributeAvType;
use BackOfficeDefaultTwigBundle\Form\Attribute\AttributeType;
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
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\Attribute\AttributeCreateEvent;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeEvent;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/attributes', name: 'admin.configuration.attributes.')]
final class AttributeController
{
    private const RESOURCE = AdminResources::ATTRIBUTE;
    private const LIST_ROUTE = 'admin.configuration.attributes.default';
    private const EDIT_ROUTE = 'admin.configuration.attributes.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/attribute/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/attribute/edit.html.twig';

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
        $form = $this->formFactory->createNamed('thelia_attribute_creation', AttributeType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'include_creation_extras' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::ATTRIBUTE_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Attribute creation',
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

        $attributeId = (int) $request->query->get('attribute_id', 0);
        $attribute = AttributeQuery::create()->findPk($attributeId);
        if ($attribute === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $attribute->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'attribute' => $attribute,
            'form' => $this->buildUpdateForm($attribute, $locale)->createView(),
            'av_create_form' => $this->buildAvCreateForm($attributeId, $locale)->createView(),
            'attribute_avs' => $this->avRows($attribute, $locale),
            'av_position_url' => $this->urls->generate('admin.configuration.attributes-av.update-position'),
            'av_position_token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_attribute_modification', AttributeType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        $attributeId = (int) $request->request->get('attribute_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::ATTRIBUTE_UPDATE,
            eventFactory: function (FormInterface $validated) use ($request): AttributeUpdateEvent {
                $event = $this->updateEvent($validated);
                $this->processAttributeValues($request, $event->getLocale() ?? $this->defaultLocale());

                return $event;
            },
            actionLabel: 'Attribute update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['attribute_id' => $attributeId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['attribute_id' => $attributeId])),
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
            event: new AttributeDeleteEvent((int) $request->get('attribute_id', 0)),
            eventName: TheliaEvents::ATTRIBUTE_DELETE,
            actionLabel: 'Attribute deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('attribute_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ATTRIBUTE_UPDATE_POSITION,
            actionLabel: 'Attribute reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/add-to-all-templates', name: 'add-to-all', methods: ['POST', 'GET'])]
    public function addToAllTemplates(Request $request): Response
    {
        return $this->dispatchTemplateBulk($request, TheliaEvents::ATTRIBUTE_ADD_TO_ALL_TEMPLATES, 'Attribute added to all templates');
    }

    #[Route('/remove-from-all-templates', name: 'rem-from-all', methods: ['POST', 'GET'])]
    public function removeFromAllTemplates(Request $request): Response
    {
        return $this->dispatchTemplateBulk($request, TheliaEvents::ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES, 'Attribute removed from all templates');
    }

    private function dispatchTemplateBulk(Request $request, string $eventName, string $label): Response
    {
        $attribute = AttributeQuery::create()->findPk((int) $request->get('attribute_id', 0));
        if ($attribute === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new AttributeEvent($attribute),
            eventName: $eventName,
            actionLabel: $label,
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): AttributeCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new AttributeCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setAddToAllTemplates((bool) ($data['add_to_all'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): AttributeUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new AttributeUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null));

        return $event;
    }

    private function processAttributeValues(Request $request, string $locale): void
    {
        $values = $request->request->all('attribute_values');
        if ($values === []) {
            return;
        }

        foreach ($values as $id => $title) {
            if (!is_string($title)) {
                continue;
            }
            $event = new AttributeAvUpdateEvent((int) $id);
            $event->setTitle($title)->setLocale($locale);
            $this->events->dispatch($event, TheliaEvents::ATTRIBUTE_AV_UPDATE);
        }
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(AttributeCreateEvent $event): array
    {
        if (!$event->hasAttribute()) {
            throw new \LogicException($this->translator->trans('No attribute was created.'));
        }
        $attribute = $event->getAttribute();

        return [\sprintf('Attribute %s (ID %d) created', (string) $attribute->getTitle(), (int) $attribute->getId()), (int) $attribute->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(AttributeUpdateEvent $event): array
    {
        if (!$event->hasAttribute()) {
            return ['Attribute modified', null];
        }
        $attribute = $event->getAttribute();

        return [\sprintf('Attribute %s (ID %d) modified', (string) $attribute->getTitle(), (int) $attribute->getId()), (int) $attribute->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $attributes = AttributeQuery::create()->orderByPosition()->find();
        $rows = [];

        foreach ($attributes as $attribute) {
            \assert($attribute instanceof Attribute);
            $attribute->setLocale($locale);
            $rows[] = $this->attributeToRow($attribute);
        }

        $createForm = $this->formFactory->createNamed('thelia_attribute_creation', AttributeType::class, [
            'locale' => $locale,
        ], [
            'include_creation_extras' => true,
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
            'update_position_url' => $this->urls->generate('admin.configuration.attributes.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeToRow(Attribute $attribute): array
    {
        $id = (int) $attribute->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this attribute'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['attribute_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this attribute'),
                modalTarget: '#attribute-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: [
                    'attribute-id' => $id,
                    'attribute-label' => (string) $attribute->getTitle(),
                ],
            ),
        ];

        return [
            'id' => $id,
            'title' => (string) $attribute->getTitle(),
            'position' => (int) $attribute->getPosition(),
            '_actions' => $actions,
        ];
    }

    private function buildUpdateForm(Attribute $attribute, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_attribute_modification', AttributeType::class, [
            'id' => $attribute->getId(),
            'locale' => $locale,
            'title' => $attribute->getTitle(),
            'chapo' => $attribute->getChapo(),
            'description' => $attribute->getDescription(),
            'postscriptum' => $attribute->getPostscriptum(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    private function buildAvCreateForm(int $attributeId, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_attributeav_creation', AttributeAvType::class, [
            'attribute_id' => $attributeId,
            'locale' => $locale,
        ], [
            'csrf_protection' => false,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function avRows(Attribute $attribute, string $locale): array
    {
        $rows = [];
        $avs = AttributeAvQuery::create()->filterByAttributeId($attribute->getId())->orderByPosition()->find();
        foreach ($avs as $av) {
            $av->setLocale($locale);
            $rows[] = [
                'id' => (int) $av->getId(),
                'title' => (string) $av->getTitle(),
                'position' => (int) $av->getPosition(),
                'delete_url' => $this->tokenizedUrl('admin.configuration.attributes-av.delete', ['attributeav_id' => (int) $av->getId()]),
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

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

use BackOfficeDefaultTwigBundle\Form\Template\TemplateType;
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
use Thelia\Core\Event\Template\TemplateAddAttributeEvent;
use Thelia\Core\Event\Template\TemplateAddFeatureEvent;
use Thelia\Core\Event\Template\TemplateCreateEvent;
use Thelia\Core\Event\Template\TemplateDeleteAttributeEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Core\Event\Template\TemplateDeleteFeatureEvent;
use Thelia\Core\Event\Template\TemplateDuplicateEvent;
use Thelia\Core\Event\Template\TemplateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Template;
use Thelia\Model\TemplateQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/templates', name: 'admin.configuration.templates.')]
final class TemplateController
{
    private const RESOURCE = AdminResources::TEMPLATE;
    private const LIST_ROUTE = 'admin.configuration.templates.default';
    private const EDIT_ROUTE = 'admin.configuration.templates.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/template/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/template/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
        private readonly \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $events,
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
        $form = $this->formFactory->createNamed('thelia_template_creation', TemplateType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::TEMPLATE_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Template creation',
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

        $templateId = (int) $request->query->get('template_id', 0);
        $template = TemplateQuery::create()->findPk($templateId);
        if ($template === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $template->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'template' => $template,
            'form' => $this->buildUpdateForm($template, $locale)->createView(),
            'features' => $this->templateFeatureRows($template),
            'attributes' => $this->templateAttributeRows($template),
            'available_features' => $this->availableFeatures($template, $locale),
            'available_attributes' => $this->availableAttributes($template, $locale),
            'feature_position_url' => $this->urls->generate('admin.configuration.templates.attributes.update-feature-position'),
            'attribute_position_url' => $this->urls->generate('admin.configuration.templates.attributes.update-attribute-position'),
            'position_token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_template_modification', TemplateType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        $templateId = (int) $request->request->get('template_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::TEMPLATE_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Template update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => $templateId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['template_id' => $templateId])),
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
            event: new TemplateDeleteEvent((int) $request->get('template_id', 0)),
            eventName: TheliaEvents::TEMPLATE_DELETE,
            actionLabel: 'Template deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/duplicate', name: 'duplicate', methods: ['POST', 'GET'])]
    public function duplicate(Request $request): Response
    {
        $sourceId = (int) $request->get('source_template_id', $request->get('template_id', 0));
        $event = new TemplateDuplicateEvent($sourceId, $this->defaultLocale());
        $newName = trim((string) $request->get('new_name', ''));

        $response = $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::TEMPLATE_DUPLICATE,
            actionLabel: 'Template duplication',
            successRoute: self::LIST_ROUTE,
        );

        if ($newName !== '' && $event->hasTemplate()) {
            $clone = $event->getTemplate();
            $updateEvent = (new \Thelia\Core\Event\Template\TemplateUpdateEvent((int) $clone->getId()))
                ->setLocale($this->defaultLocale())
                ->setTemplateName($newName);
            $this->events->dispatch($updateEvent, TheliaEvents::TEMPLATE_UPDATE);
        }

        return $response;
    }

    #[Route('/features/list', name: 'features.list', methods: ['GET'])]
    public function featuresList(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $template = TemplateQuery::create()->findPk((int) $request->query->get('template_id', 0));
        if ($template === null) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/configuration/template/_features_table.html.twig', [
            'template' => $template,
            'features' => $this->templateFeatureRows($template),
            'feature_position_url' => $this->urls->generate('admin.configuration.templates.attributes.update-feature-position'),
            'position_token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/features/add', name: 'features.add', methods: ['POST', 'GET'])]
    public function addFeature(Request $request): Response
    {
        $template = TemplateQuery::create()->findPk((int) $request->get('template_id', 0));
        if ($template === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new TemplateAddFeatureEvent($template, (int) $request->get('feature_id', 0)),
            eventName: TheliaEvents::TEMPLATE_ADD_FEATURE,
            actionLabel: 'Template feature added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => (int) $template->getId()],
        );
    }

    #[Route('/features/delete', name: 'features.delete', methods: ['POST', 'GET'])]
    public function deleteFeature(Request $request): Response
    {
        $template = TemplateQuery::create()->findPk((int) $request->get('template_id', 0));
        if ($template === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new TemplateDeleteFeatureEvent($template, (int) $request->get('feature_id', 0)),
            eventName: TheliaEvents::TEMPLATE_DELETE_FEATURE,
            actionLabel: 'Template feature removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => (int) $template->getId()],
        );
    }

    #[Route('/attributes/list', name: 'attributes.list', methods: ['GET'])]
    public function attributesList(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $template = TemplateQuery::create()->findPk((int) $request->query->get('template_id', 0));
        if ($template === null) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/configuration/template/_attributes_table.html.twig', [
            'template' => $template,
            'attributes' => $this->templateAttributeRows($template),
            'attribute_position_url' => $this->urls->generate('admin.configuration.templates.attributes.update-attribute-position'),
            'position_token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/attributes/add', name: 'attributes.add', methods: ['POST', 'GET'])]
    public function addAttribute(Request $request): Response
    {
        $template = TemplateQuery::create()->findPk((int) $request->get('template_id', 0));
        if ($template === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new TemplateAddAttributeEvent($template, (int) $request->get('attribute_id', 0)),
            eventName: TheliaEvents::TEMPLATE_ADD_ATTRIBUTE,
            actionLabel: 'Template attribute added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => (int) $template->getId()],
        );
    }

    #[Route('/attributes/delete', name: 'attributes.delete', methods: ['POST', 'GET'])]
    public function deleteAttribute(Request $request): Response
    {
        $template = TemplateQuery::create()->findPk((int) $request->get('template_id', 0));
        if ($template === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new TemplateDeleteAttributeEvent($template, (int) $request->get('attribute_id', 0)),
            eventName: TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE,
            actionLabel: 'Template attribute removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['template_id' => (int) $template->getId()],
        );
    }

    private function createEvent(FormInterface $validated): TemplateCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new TemplateCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTemplateName((string) ($data['name'] ?? ''));

        return $event;
    }

    private function updateEvent(FormInterface $validated): TemplateUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new TemplateUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTemplateName((string) ($data['name'] ?? ''));

        return $event;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(TemplateCreateEvent $event): array
    {
        if (!$event->hasTemplate()) {
            throw new \LogicException($this->translator->trans('No template was created.'));
        }
        $template = $event->getTemplate();

        return [\sprintf('Template %s (ID %d) created', (string) $template->getName(), (int) $template->getId()), (int) $template->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(TemplateUpdateEvent $event): array
    {
        if (!$event->hasTemplate()) {
            return ['Template modified', null];
        }
        $template = $event->getTemplate();

        return [\sprintf('Template %s (ID %d) modified', (string) $template->getName(), (int) $template->getId()), (int) $template->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $templates = TemplateQuery::create()->orderById()->find();
        $rows = [];

        foreach ($templates as $template) {
            \assert($template instanceof Template);
            $template->setLocale($locale);
            $rows[] = $this->templateToRow($template);
        }

        $createForm = $this->formFactory->createNamed('thelia_template_creation', TemplateType::class, [
            'locale' => $locale,
        ], [
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateToRow(Template $template): array
    {
        $id = (int) $template->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this template'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['template_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Duplicate'),
                modalTarget: '#template-duplicate-modal',
                grantedAttribute: AccessManager::CREATE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['template-id' => $id, 'template-name' => (string) $template->getName()],
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this template'),
                modalTarget: '#template-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: [
                    'template-id' => $id,
                    'template-label' => (string) $template->getName(),
                ],
            ),
        ];

        return [
            'id' => $id,
            'name' => (string) $template->getName(),
            '_actions' => $actions,
        ];
    }

    private function buildUpdateForm(Template $template, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_template_modification', TemplateType::class, [
            'id' => $template->getId(),
            'locale' => $locale,
            'name' => $template->getName(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateFeatureRows(Template $template): array
    {
        $rows = [];
        $links = FeatureTemplateQuery::create()
            ->filterByTemplateId($template->getId())
            ->orderByPosition()
            ->joinWithFeature()
            ->find();

        foreach ($links as $link) {
            $feature = $link->getFeature();
            $feature->setLocale($template->getLocale() ?? $this->defaultLocale());
            $rows[] = [
                'id' => (int) $feature->getId(),
                'title' => (string) $feature->getTitle(),
                'position' => (int) $link->getPosition(),
                'delete_url' => $this->tokenizedUrl('admin.configuration.templates.features.delete', ['template_id' => (int) $template->getId(), 'feature_id' => (int) $feature->getId()]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateAttributeRows(Template $template): array
    {
        $rows = [];
        $links = AttributeTemplateQuery::create()
            ->filterByTemplateId($template->getId())
            ->orderByPosition()
            ->joinWithAttribute()
            ->find();

        foreach ($links as $link) {
            $attribute = $link->getAttribute();
            $attribute->setLocale($template->getLocale() ?? $this->defaultLocale());
            $rows[] = [
                'id' => (int) $attribute->getId(),
                'title' => (string) $attribute->getTitle(),
                'position' => (int) $link->getPosition(),
                'delete_url' => $this->tokenizedUrl('admin.configuration.templates.attributes.delete', ['template_id' => (int) $template->getId(), 'attribute_id' => (int) $attribute->getId()]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableFeatures(Template $template, string $locale): array
    {
        $existingIds = FeatureTemplateQuery::create()
            ->filterByTemplateId($template->getId())
            ->select(['FeatureId'])
            ->find()
            ->toArray();

        $features = FeatureQuery::create()
            ->filterById($existingIds, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN)
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($features as $feature) {
            $feature->setLocale($locale);
            $items[] = [
                'id' => (int) $feature->getId(),
                'title' => (string) $feature->getTitle(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableAttributes(Template $template, string $locale): array
    {
        $existingIds = AttributeTemplateQuery::create()
            ->filterByTemplateId($template->getId())
            ->select(['AttributeId'])
            ->find()
            ->toArray();

        $attributes = AttributeQuery::create()
            ->filterById($existingIds, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN)
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($attributes as $attribute) {
            $attribute->setLocale($locale);
            $items[] = [
                'id' => (int) $attribute->getId(),
                'title' => (string) $attribute->getTitle(),
            ];
        }

        return $items;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
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

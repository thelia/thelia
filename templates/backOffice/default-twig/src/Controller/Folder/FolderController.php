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

namespace BackOfficeDefaultTwigBundle\Controller\Folder;

use BackOfficeDefaultTwigBundle\Form\Folder\FolderType;
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
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/folders', name: 'admin.folders.')]
final class FolderController
{
    private const RESOURCE = AdminResources::FOLDER;
    private const LIST_ROUTE = 'admin.folders.default';
    private const EDIT_ROUTE = 'admin.folders.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/folder/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/folder/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $parentId = (int) $request->query->get('folder_id', 0);

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext($parentId)));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_folder_creation', FolderType::class, [
            'locale' => $this->defaultLocale(),
        ], ['csrf_protection' => false]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::FOLDER_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Folder creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{folder_id}', name: 'update', methods: ['GET'], requirements: ['folder_id' => '\d+'])]
    public function updateView(int $folder_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $folder = FolderQuery::create()->findPk($folder_id);
        if ($folder === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $folder->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'folder' => $folder,
            'form' => $this->buildUpdateForm($folder, $locale)->createView(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_folder_modification', FolderType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        $folderId = (int) $request->request->get('folder_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::FOLDER_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Folder update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['folder_id' => $folderId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['folder_id' => $folderId])),
        );
    }

    #[Route('/seo/save', name: 'seo.save', methods: ['POST'])]
    public function processSeo(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $folderId = (int) $request->request->get('folder_id', $request->request->get('id', 0));
        $event = new UpdateSeoEvent(
            $folderId,
            (string) $request->request->get('locale', $this->defaultLocale()),
            $request->request->get('url') !== null ? (string) $request->request->get('url') : null,
            $request->request->get('meta_title') !== null ? (string) $request->request->get('meta_title') : null,
            $request->request->get('meta_description') !== null ? (string) $request->request->get('meta_description') : null,
            $request->request->get('meta_keywords') !== null ? (string) $request->request->get('meta_keywords') : null,
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::FOLDER_UPDATE_SEO,
            actionLabel: 'Folder SEO update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['folder_id' => $folderId],
        );
    }

    #[Route('/toggle-online', name: 'toggle-online', methods: ['GET', 'POST'])]
    public function toggleOnline(Request $request): Response
    {
        $folder = FolderQuery::create()->findPk((int) $request->get('folder_id', 0));
        if ($folder === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new FolderToggleVisibilityEvent($folder),
            eventName: TheliaEvents::FOLDER_TOGGLE_VISIBILITY,
            actionLabel: 'Folder visibility',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('folder_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::FOLDER_UPDATE_POSITION,
            actionLabel: 'Folder reorder',
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
            event: new FolderDeleteEvent((int) $request->get('folder_id', 0)),
            eventName: TheliaEvents::FOLDER_DELETE,
            actionLabel: 'Folder deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): FolderCreateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new FolderCreateEvent();
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setParent((int) ($data['parent'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): FolderUpdateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new FolderUpdateEvent((int) ($data['id'] ?? 0));
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setParent((int) ($data['parent'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null));

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(int $parentId): array
    {
        $locale = $this->defaultLocale();
        $folders = FolderQuery::create()->filterByParent($parentId)->orderByPosition()->find();
        $rows = [];
        foreach ($folders as $folder) {
            \assert($folder instanceof Folder);
            $folder->setLocale($locale);
            $rows[] = $this->folderToRow($folder);
        }

        $createForm = $this->formFactory->createNamed('thelia_folder_creation', FolderType::class, [
            'locale' => $locale,
            'parent' => $parentId,
        ], ['csrf_protection' => false]);

        return [
            'rows' => $rows,
            'parent_id' => $parentId,
            'create_form' => $createForm->createView(),
            'update_position_url' => $this->urls->generate('admin.folders.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function folderToRow(Folder $folder): array
    {
        $id = (int) $folder->getId();
        $actions = [
            new RowAction(kind: 'edit', label: $this->translator->trans('Edit'), href: $this->urls->generate(self::EDIT_ROUTE, ['folder_id' => $id]), grantedAttribute: AccessManager::UPDATE, grantedSubject: self::RESOURCE),
            new RowAction(kind: 'delete', label: $this->translator->trans('Delete'), modalTarget: '#folder-delete-modal', grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE, dataAttributes: ['folder-id' => $id, 'folder-label' => (string) $folder->getTitle()]),
        ];

        return [
            'id' => $id,
            'title' => (string) $folder->getTitle(),
            'visible' => (bool) $folder->getVisible(),
            'position' => (int) $folder->getPosition(),
            'children_url' => $this->urls->generate(self::LIST_ROUTE, ['folder_id' => $id]),
            'toggle_visible_url' => $this->tokenizedUrl('admin.folders.toggle-online', ['folder_id' => $id]),
            '_actions' => $actions,
        ];
    }

    private function buildUpdateForm(Folder $folder, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_folder_modification', FolderType::class, [
            'id' => $folder->getId(),
            'locale' => $locale,
            'title' => $folder->getTitle(),
            'parent' => $folder->getParent(),
            'visible' => (bool) $folder->getVisible(),
            'chapo' => $folder->getChapo(),
            'description' => $folder->getDescription(),
            'postscriptum' => $folder->getPostscriptum(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
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

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

use BackOfficeDefaultTwigBundle\Form\Content\ContentType;
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
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/content', name: 'admin.content.')]
final class ContentController
{
    private const RESOURCE = AdminResources::CONTENT;
    private const LIST_ROUTE = 'admin.folders.default';
    private const EDIT_ROUTE = 'admin.content.update';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/content/edit.html.twig';

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

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_content_creation', ContentType::class, [
            'locale' => $this->defaultLocale(),
        ], ['csrf_protection' => false]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::CONTENT_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Content creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{content_id}', name: 'update', methods: ['GET'], requirements: ['content_id' => '\d+'])]
    public function updateView(int $content_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $content = ContentQuery::create()->findPk($content_id);
        if ($content === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $content->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'content' => $content,
            'form' => $this->buildUpdateForm($content, $locale)->createView(),
            'folders' => $this->folderChoices($locale),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_content_modification', ContentType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        $contentId = (int) $request->request->get('content_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::CONTENT_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Content update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['content_id' => $contentId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['content_id' => $contentId])),
        );
    }

    #[Route('/seo/save', name: 'seo.save', methods: ['POST'])]
    public function processSeo(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $contentId = (int) $request->request->get('content_id', $request->request->get('id', 0));
        $event = new UpdateSeoEvent(
            $contentId,
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
            eventName: TheliaEvents::CONTENT_UPDATE_SEO,
            actionLabel: 'Content SEO update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['content_id' => $contentId],
        );
    }

    #[Route('/toggle-online', name: 'toggle-online', methods: ['GET', 'POST'])]
    public function toggleOnline(Request $request): Response
    {
        $content = ContentQuery::create()->findPk((int) $request->get('content_id', 0));
        if ($content === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $folderId = (int) $content->getDefaultFolderId();

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ContentToggleVisibilityEvent($content),
            eventName: TheliaEvents::CONTENT_TOGGLE_VISIBILITY,
            actionLabel: 'Content visibility',
            successRoute: self::LIST_ROUTE,
            successParameters: ['folder_id' => $folderId],
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('content_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CONTENT_UPDATE_POSITION,
            actionLabel: 'Content reorder',
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
            event: new ContentDeleteEvent((int) $request->get('content_id', 0)),
            eventName: TheliaEvents::CONTENT_DELETE,
            actionLabel: 'Content deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/additional-folder/add', name: 'additional-folder.add', methods: ['POST'])]
    public function addAdditionalFolder(Request $request): Response
    {
        $contentId = (int) $request->request->get('content_id', 0);
        $folderId = (int) $request->request->get('additional_folder_id', 0);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ContentAddFolderEvent($contentId, $folderId),
            eventName: TheliaEvents::CONTENT_ADD_FOLDER,
            actionLabel: 'Content additional folder add',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['content_id' => $contentId],
        );
    }

    #[Route('/additional-folder/delete', name: 'additional-folder.delete', methods: ['POST', 'GET'])]
    public function deleteAdditionalFolder(Request $request): Response
    {
        $contentId = (int) $request->get('content_id', 0);
        $folderId = (int) $request->get('additional_folder_id', 0);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ContentRemoveFolderEvent($contentId, $folderId),
            eventName: TheliaEvents::CONTENT_REMOVE_FOLDER,
            actionLabel: 'Content additional folder remove',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['content_id' => $contentId],
        );
    }

    private function createEvent(FormInterface $validated): ContentCreateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new ContentCreateEvent();
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setDefaultFolder((int) ($data['default_folder'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): ContentUpdateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new ContentUpdateEvent((int) ($data['id'] ?? 0));
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setDefaultFolder((int) ($data['default_folder'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null));

        return $event;
    }

    private function buildUpdateForm(Content $content, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_content_modification', ContentType::class, [
            'id' => $content->getId(),
            'locale' => $locale,
            'title' => $content->getTitle(),
            'default_folder' => $content->getDefaultFolderId(),
            'visible' => (bool) $content->getVisible(),
            'chapo' => $content->getChapo(),
            'description' => $content->getDescription(),
            'postscriptum' => $content->getPostscriptum(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    /** @return list<array{id: int, title: string}> */
    private function folderChoices(string $locale): array
    {
        $folders = FolderQuery::create()->orderByPosition()->find();
        $rows = [];
        foreach ($folders as $folder) {
            $folder->setLocale($locale);
            $rows[] = ['id' => (int) $folder->getId(), 'title' => (string) $folder->getTitle()];
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
}

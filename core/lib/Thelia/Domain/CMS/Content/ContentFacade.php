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

namespace Thelia\Domain\CMS\Content;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Domain\CMS\Content\DTO\ContentCreateDTO;
use Thelia\Domain\CMS\Content\DTO\ContentSeoDTO;
use Thelia\Domain\CMS\Content\DTO\ContentUpdateDTO;
use Thelia\Domain\CMS\Content\Exception\ContentNotFoundException;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;

final readonly class ContentFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(ContentCreateDTO $dto): Content
    {
        $event = new ContentCreateEvent();
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDefaultFolder($dto->defaultFolderId)
            ->setVisible($dto->visible);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_CREATE);

        return $event->getContent();
    }

    public function update(int $contentId, ContentUpdateDTO $dto): Content
    {
        $event = new ContentUpdateEvent($contentId);
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setDefaultFolder($dto->defaultFolderId)
            ->setVisible($dto->visible)
            ->setChapo($dto->chapo)
            ->setDescription($dto->description)
            ->setPostscriptum($dto->postscriptum);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_UPDATE);

        return $event->getContent();
    }

    public function delete(int $contentId): void
    {
        $event = new ContentDeleteEvent($contentId);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_DELETE);
    }

    public function toggleVisibility(int $contentId): Content
    {
        $content = $this->getById($contentId);

        if (null === $content) {
            throw ContentNotFoundException::withId($contentId);
        }

        $event = new ContentToggleVisibilityEvent($content);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_TOGGLE_VISIBILITY);

        return $event->getContent();
    }

    public function updatePosition(int $contentId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdatePositionEvent($contentId, $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_UPDATE_POSITION);
    }

    public function addFolder(int $contentId, int $folderId): void
    {
        $content = $this->getById($contentId);

        if (null === $content) {
            throw ContentNotFoundException::withId($contentId);
        }

        $event = new ContentAddFolderEvent($content, $folderId);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_ADD_FOLDER);
    }

    public function removeFolder(int $contentId, int $folderId): void
    {
        $content = $this->getById($contentId);

        if (null === $content) {
            throw ContentNotFoundException::withId($contentId);
        }

        $event = new ContentRemoveFolderEvent($content, $folderId);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_REMOVE_FOLDER);
    }

    public function updateSeo(int $contentId, ContentSeoDTO $dto): Content
    {
        $content = $this->getById($contentId);

        if (null === $content) {
            throw ContentNotFoundException::withId($contentId);
        }

        $event = new UpdateSeoEvent($contentId);
        $event
            ->setLocale($dto->locale)
            ->setUrl($dto->url)
            ->setMetaTitle($dto->metaTitle)
            ->setMetaDescription($dto->metaDescription)
            ->setMetaKeywords($dto->metaKeywords);

        $this->dispatcher->dispatch($event, TheliaEvents::CONTENT_UPDATE_SEO);

        $content->reload();

        return $content;
    }

    public function getById(int $contentId): ?Content
    {
        return ContentQuery::create()->findPk($contentId);
    }

    public function getByFolder(int $folderId, bool $visibleOnly = false): array
    {
        $query = ContentQuery::create()
            ->useContentFolderQuery()
                ->filterByFolderId($folderId)
            ->endUse()
            ->orderByPosition();

        if ($visibleOnly) {
            $query->filterByVisible(true);
        }

        return $query->find()->getData();
    }
}

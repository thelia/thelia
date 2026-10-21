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

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\Content\ContentUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Domain\CMS\Publication\ContentFolderPublicationRule;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\Media\DeletedItemMedia;
use Thelia\Domain\Media\PendingMediaDeletions;
use Thelia\Model\Content as ContentModel;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Map\ContentTableMap;

/**
 * Class Content.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class Content extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly PublicationService $publicationService,
        private readonly ContentFolderPublicationRule $folderPublicationRule,
    ) {
    }

    public function create(ContentCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $content = (new ContentModel())

            ->setVisible((int) $event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->create($event->getDefaultFolder());

        $event->setContent($content);
    }

    /**
     * process update content.
     *
     * @throws PropelException
     * @throws \Exception
     */
    public function update(ContentUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $content = ContentQuery::create()->findPk($event->getContentId())) {
            return;
        }
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $content
                ->setVisible((int) $event->getVisible())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save($con);

            $content->setDefaultFolder($event->getDefaultFolder());

            $event->setContent($content);
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();

            throw $e;
        }
    }

    /**
     * Change Content SEO.
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher): object
    {
        return $this->genericUpdateSeo(ContentQuery::create(), $event, $dispatcher);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdateDelegatePosition(
            ContentFolderQuery::create()
                ->filterByContentId($event->getObjectId())
                ->filterByFolderId($event->getReferrerId()),
            $event,
            $dispatcher,
        );
    }

    public function toggleVisibility(ContentToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $content = $event->getContent();

        if (null === $content) {
            return;
        }

        $content
            ->setVisible($content->getVisible() ? 0 : 1)
            ->save();

        $event->setContent($content);
    }

    /**
     * Publish, schedule, cancel or take offline. The state the content reached is left
     * on the event; a refused gesture throws a `PublicationException` and writes nothing.
     */
    public function updatePublication(ContentUpdatePublicationEvent $event): void
    {
        $content = $event->getContent();

        if (null === $content) {
            return;
        }

        $event->setStatus($this->publicationService->apply($content, $event->request));
    }

    public function delete(ContentDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null === $content = ContentQuery::create()->findPk($event->getContentId())) {
            return;
        }
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
        $con->beginTransaction();
        PendingMediaDeletions::begin();

        try {
            $defaultFolderId = $content->getDefaultFolderId();

            PendingMediaDeletions::add(DeletedItemMedia::of($content));

            $content
                ->delete($con);

            $event->setDefaultFolderId($defaultFolderId);
            $event->setContent($content);

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollback();
            PendingMediaDeletions::rollBack();

            throw $e;
        }

        PendingMediaDeletions::commit($dispatcher);
    }

    /**
     * associate a folder to a content if the association already does not exists.
     */
    public function addFolder(ContentAddFolderEvent $event): void
    {
        if (ContentFolderQuery::create()
            ->filterByContent($event->getContent())
            ->filterByFolderId($event->getFolderId())
            ->count() > 0
        ) {
            return;
        }
        $contentFolder = (new ContentFolder())
            ->setFolderId((int) $event->getFolderId())
            ->setContent($event->getContent())
            ->setDefaultFolder(0);

        $contentFolder
            ->setPosition($contentFolder->getNextPosition())
            ->save();
    }

    public function removeFolder(ContentRemoveFolderEvent $event): void
    {
        $contentFolder = ContentFolderQuery::create()
            ->filterByContent($event->getContent())
            ->filterByFolderId($event->getFolderId())
            ->findOne();

        if (null !== $contentFolder) {
            $contentFolder->delete();
        }
    }

    /**
     * Check if is a content view and if content_id is visible. A content whose default
     * folder is not online yet (a draft or a scheduled folder) is not on the shop either.
     */
    public function viewCheck(ViewCheckEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ('content' !== $event->getView()) {
            return;
        }
        $content = ContentQuery::create()
            ->filterById($event->getViewId())
            ->filterByVisible(1)
            ->where($this->folderPublicationRule->outsideAnUnreleasedFolderClause(ContentTableMap::COL_ID))
            ->count();

        if (0 === $content) {
            $dispatcher->dispatch($event, TheliaEvents::VIEW_CONTENT_ID_NOT_VISIBLE);
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewContentIdNotVisible(ViewCheckEvent $event): void
    {
        throw new NotFoundHttpException();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONTENT_CREATE => ['create', 128],
            TheliaEvents::CONTENT_UPDATE => ['update', 128],
            TheliaEvents::CONTENT_DELETE => ['delete', 128],
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
            TheliaEvents::CONTENT_UPDATE_PUBLICATION => ['updatePublication', 128],

            TheliaEvents::CONTENT_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::CONTENT_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::CONTENT_ADD_FOLDER => ['addFolder', 128],
            TheliaEvents::CONTENT_REMOVE_FOLDER => ['removeFolder', 128],

            TheliaEvents::VIEW_CHECK => ['viewCheck', 128],
            TheliaEvents::VIEW_CONTENT_ID_NOT_VISIBLE => ['viewContentIdNotVisible', 128],
        ];
    }
}

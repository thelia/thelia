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

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\Folder\FolderUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\Media\DeletedItemMedia;
use Thelia\Domain\Media\PendingMediaDeletions;
use Thelia\Model\Folder as FolderModel;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\FolderTableMap;

/**
 * Class Folder.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Folder extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly PublicationService $publicationService,
    ) {
    }

    /**
     * Publish, schedule, cancel or take offline. The state the folder reached is left
     * on the event; a refused gesture throws a `PublicationException` and writes nothing.
     */
    public function updatePublication(FolderUpdatePublicationEvent $event): void
    {
        $folder = $event->getFolder();

        if (null === $folder) {
            return;
        }

        $event->setStatus($this->publicationService->apply($folder, $event->request));
    }

    public function update(FolderUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $folder
                ->setParent((int) $event->getParent())
                ->setVisible((int) $event->getVisible())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save();

            $event->setFolder($folder);
        }
    }

    /**
     * Change Folder SEO.
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher): object
    {
        return $this->genericUpdateSeo(FolderQuery::create(), $event, $dispatcher);
    }

    public function delete(FolderDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $con = Propel::getWriteConnection(FolderTableMap::DATABASE_NAME);
            $con->beginTransaction();
            PendingMediaDeletions::begin();

            try {
                PendingMediaDeletions::add(DeletedItemMedia::of($folder));

                $folder
                    ->delete($con);

                $event->setFolder($folder);

                $con->commit();
            } catch (\Throwable $e) {
                $con->rollback();
                PendingMediaDeletions::rollBack();

                throw $e;
            }

            PendingMediaDeletions::commit($dispatcher);
        }
    }

    public function create(FolderCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $folder = new FolderModel();

        $folder
            ->setParent((int) $event->getParent())
            ->setVisible((int) $event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setFolder($folder);
    }

    public function toggleVisibility(FolderToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $folder = $event->getFolder();
        if (null === $folder) {
            return;
        }

        $folder
            ->setVisible($folder->getVisible() ? 0 : 1)
            ->save();

        $event->setFolder($folder);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getObjectId())) {
            switch ($event->getMode()) {
                case UpdatePositionEvent::POSITION_ABSOLUTE:
                    $folder->changeAbsolutePosition($event->getPosition());
                    break;
                case UpdatePositionEvent::POSITION_DOWN:
                    $folder->movePositionDown();
                    break;
                case UpdatePositionEvent::POSITION_UP:
                    $folder->movePositionUp();
                    break;
            }
        }
    }

    /**
     * Check if is a folder view and if folder_id is visible.
     */
    public function viewCheck(ViewCheckEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ('folder' !== $event->getView()) {
            return;
        }
        $folder = FolderQuery::create()
            ->filterById($event->getViewId())
            ->filterByVisible(1)
            ->count();

        if (0 === $folder) {
            $dispatcher->dispatch($event, TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE);
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewFolderIdNotVisible(ViewCheckEvent $event): void
    {
        throw new NotFoundHttpException();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FOLDER_CREATE => ['create', 128],
            TheliaEvents::FOLDER_UPDATE => ['update', 128],
            TheliaEvents::FOLDER_DELETE => ['delete', 128],
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
            TheliaEvents::FOLDER_UPDATE_PUBLICATION => ['updatePublication', 128],

            TheliaEvents::FOLDER_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::FOLDER_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::VIEW_CHECK => ['viewCheck', 128],
            TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE => ['viewFolderIdNotVisible', 128],
        ];
    }
}

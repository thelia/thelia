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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

class FileUrlModelToResourceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function addFileUrl(ModelToResourceEvent $modelToResourceEvent): void
    {
        /** @var ItemFileResourceInterface $resource */
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ItemFileResourceInterface) {
            return;
        }

        $documentType = $resource::getFileType();

        $baseSourceFilePath = ConfigQuery::read($documentType.'s_library_path');

        if (null === $baseSourceFilePath) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.$documentType.'s';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        $event = 'image' === $documentType ? new ImageEvent() : new DocumentEvent();
        $eventName = 'image' === $documentType ? TheliaEvents::IMAGE_PROCESS : TheliaEvents::DOCUMENT_PROCESS;
        $sourceFilePath = \sprintf(
            '%s/%s/%s',
            $baseSourceFilePath,
            $resource::getItemType(),
            $resource->getFile(),
        );
        $event->setSourceFilepath($sourceFilePath);
        $event->setCacheSubdirectory($resource::getItemType());

        $this->eventDispatcher->dispatch($event, $eventName);

        $urlGetter = 'image' === $documentType ? 'getFileUrl' : 'getDocumentUrl';
        $resource->setFileUrl($event->{$urlGetter}());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addFileUrl', 0],
            ],
        ];
    }
}

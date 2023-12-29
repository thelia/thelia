<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Bridge\Propel\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Files\FileModelInterface;

readonly class ItemFileResourceService
{
    public function __construct(
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function createItemFile(
        int $parentId,
        FileModelInterface $fileModel
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }
        /** @var UploadedFile $file */
        $file = $request->files->get('fileToUpload');

        if (!$file->isValid()) {
            throw new FileException($file->getErrorMessage());
        }

        $fileModel->setParentId($parentId)
            ->setVisible(filter_var($request->get('visible'), \FILTER_VALIDATE_BOOLEAN))
            ->setPosition($request->get('position'));

        $i18ns = json_decode($request->get('i18ns'), true);

        foreach ($i18ns as $locale => $i18n) {
            $fileModel->setLocale($locale)
                ->setTitle($i18n['title'] ?? null)
                ->setDescription($i18n['description'])
                ->setChapo($i18n['chapo'])
                ->setPostscriptum($i18n['postscriptum']);
        }

        $fileEvent = new FileCreateOrUpdateEvent($parentId);
        $fileEvent->setModel($fileModel);
        $fileEvent->setUploadedFile($file);

        $this->eventDispatcher->dispatch(
            $fileEvent,
            TheliaEvents::DOCUMENT_SAVE
        );
    }
}

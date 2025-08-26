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

namespace Thelia\Core\File\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\FileManager;
use Thelia\Model\Lang;

readonly class FileProcessorService
{
    public function __construct(
        private FileManager $fileManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws ProcessFileException If file processing fails
     */
    public function processFile(
        EventDispatcherInterface $eventDispatcher,
        UploadedFile $fileBeingUploaded,
        int $parentId,
        string $parentType,
        string $objectType,
        array $validMimeTypes = [],
        array $extBlackList = [],
        string $moduleRight = 'thelia',
    ): FileCreateOrUpdateEvent {
        // Validate if file is too big
        if (1 === $fileBeingUploaded->getError()) {
            $message = $this->translator->trans(
                'File is too large, please retry with a file having a size less than %size%.',
                ['%size%' => \ini_get('upload_max_filesize')],
                'core',
            );

            throw new ProcessFileException($message, 403);
        }

        $message = null;
        $realFileName = $fileBeingUploaded->getClientOriginalName();

        if ([] !== $validMimeTypes) {
            $mimeType = $fileBeingUploaded->getMimeType();

            if (!isset($validMimeTypes[$mimeType])) {
                $message = $this->translator->trans(
                    'Only files having the following mime type are allowed: %types%',
                    ['%types%' => implode(', ', array_keys($validMimeTypes))],
                );
            } else {
                $regex = '#^(.+)\\.('.implode('|', $validMimeTypes[$mimeType]).')$#i';

                if (!preg_match($regex, $realFileName)) {
                    $message = $this->translator->trans(
                        "There's a conflict between your file extension \"%ext\" and the mime type \"%mime\"",
                        [
                            '%mime' => $mimeType,
                            '%ext' => $fileBeingUploaded->getClientOriginalExtension(),
                        ],
                    );
                }
            }
        }

        if ([] !== $extBlackList) {
            $regex = '#^(.+)\\.('.implode('|', $extBlackList).')$#i';

            if (preg_match($regex, $realFileName)) {
                $message = $this->translator->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => $fileBeingUploaded->getClientOriginalExtension(),
                    ],
                );
            }
        }

        if (null !== $message) {
            throw new ProcessFileException($message, 415);
        }

        $fileModel = $this->fileManager->getModelInstance($objectType, $parentType);

        $parentModel = $fileModel->getParentFileModel();

        $defaultTitle = $parentModel->getTitle();

        if (empty($defaultTitle) && 'image' !== $objectType) {
            $defaultTitle = $fileBeingUploaded->getClientOriginalName();
        }

        $fileModel
            ->setParentId($parentId)
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($defaultTitle);

        $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent($parentId);
        $fileCreateOrUpdateEvent->setModel($fileModel);
        $fileCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);
        $fileCreateOrUpdateEvent->setParentName($parentModel->getTitle());

        // Dispatch Event to the Action
        $eventDispatcher->dispatch(
            $fileCreateOrUpdateEvent,
            TheliaEvents::IMAGE_SAVE,
        );

        return $fileCreateOrUpdateEvent;
    }
}

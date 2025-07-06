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

namespace Thelia\Files\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Files\FileManager;
use Thelia\Files\FileModelInterface;

readonly class FileDeleteService
{
    public function __construct(
        private FileManager $fileManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \Exception If file deletion fails
     */
    public function deleteFile(
        EventDispatcherInterface $eventDispatcher,
        int $fileId,
        string $parentType,
        string $objectType,
        string $eventName,
        string $moduleRight = 'thelia',
    ): string {
        $modelInstance = $this->fileManager->getModelInstance($objectType, $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($fileId);

        if ($model === null) {
            throw new \Exception('File not found');
        }

        // Feed event
        $fileDeleteEvent = new FileDeleteEvent($model);

        // Dispatch Event to the Action
        try {
            $eventDispatcher->dispatch($fileDeleteEvent, $eventName);

            $message = $this->translator->trans(
                '%obj%s deleted successfully',
                ['%obj%' => ucfirst($objectType)],
                'image'
            );
        } catch (\Exception $exception) {
            $message = $this->translator->trans(
                'Fail to delete %obj% for %id% with parent id %parentId% (Exception : %e%)',
                [
                    '%obj%' => $objectType,
                    '%id%' => $fileDeleteEvent->getFileToDelete()->getId(),
                    '%parentId%' => $fileDeleteEvent->getFileToDelete()->getParentId(),
                    '%e%' => $exception->getMessage(),
                ]
            );

            throw new \Exception($message, 0, $exception);
        }

        return $message;
    }

    public function getFileModelInstance(string $objectType, string $parentType): FileModelInterface
    {
        return $this->fileManager->getModelInstance($objectType, $parentType);
    }

    public function getFileById(string $objectType, string $parentType, int $fileId): ?FileModelInterface
    {
        $modelInstance = $this->fileManager->getModelInstance($objectType, $parentType);

        return $modelInstance->getQueryInstance()->findPk($fileId);
    }
}
